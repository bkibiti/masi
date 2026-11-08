<?php

namespace App\Http\Controllers;

use App\Models\ContributionSetting;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberPenalty;
use App\Services\ContributionLedger;
use App\Services\MemberSummaryService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * "My Statement": the logged-in member's own page. Always scoped to auth()->user()->member,
 * never a route parameter.
 */
class MyStatementController extends Controller
{
    public function index(Request $request, ContributionLedger $ledger, MemberSummaryService $summaries): View
    {
        $member = $request->user()->member;
        $year = (int) $request->query('year', now()->year);
        $data = $this->data($member, $year, $ledger);

        $summary = $summaries->build($member, $data['payments'], $data['statement']);
        $loans = $member->loans()->with(['loanType', 'installments', 'penalties'])->get();

        return view('account.statement', [
            ...$data,
            'years' => range(now()->year - 2, now()->year + 1),
            'summary' => $summary,
            'status' => $this->status($summary),
            'upcoming' => $this->upcoming($member, $summary),
            'rules' => $this->rules($summary),
            'guarantees' => $this->guarantees($member),
            'loans' => $loans,
            'contributedThisYear' => (float) $data['payments']
                ->filter(fn ($p) => $p->paid_on->year === now()->year && in_array($p->paymentType?->ledger_role, ['monthly_fee', 'penalty', 'none'], true))
                ->sum('amount'),
        ]);
    }

    public function pdf(Request $request, ContributionLedger $ledger): Response
    {
        $member = $request->user()->member;
        $year = (int) $request->query('year', now()->year);

        return PDF::loadView('account.statement_pdf', $this->data($member, $year, $ledger))
            ->download("statement-{$member->id}-{$year}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Member $member, int $year, ContributionLedger $ledger): array
    {
        $statement = $ledger->statementForMember($member, $year);

        $penaltyTotals = MemberPenalty::whereIn('member_due_id', $member->dues()->pluck('id'))
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as paid, COALESCE(SUM(amount - amount_paid), 0) as owed')
            ->first();

        $nextDue = MemberDue::where('member_id', $member->id)
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->first();

        $payments = $member->payments()->with(['paymentType', 'paymentMethod'])->reorder()->orderByDesc('paid_on')->orderByDesc('id')->get();

        return [
            'member' => $member,
            'year' => $year,
            'statement' => $statement,
            'penalties_paid' => (float) $penaltyTotals->paid,
            'penalties_owed' => (float) $penaltyTotals->owed,
            'next_due' => $nextDue,
            'payments' => $payments,
            'feeChargeRecords' => $ledger->feeCharges($member),
        ];
    }

    /**
     * Overall badge + one plain-language sentence for the member.
     *
     * @return array{label: string, tone: string, message: string}
     */
    private function status(array $summary): array
    {
        $arrears = (float) $summary['arrears']['total'];
        $loan = $summary['loan'];
        $loanOverdue = (float) ($summary['due']['loan_overdue'] ?? 0);
        $upcoming = $summary['due']['loan_upcoming'] ?? [];

        $parts = [];
        $parts[] = $arrears > 0
            ? 'you have <b>'.formatAmount($arrears).'</b> in contribution arrears (unpaid fees, penalties or fines).'
            : 'your contributions are fully paid up to date.';

        if ($loan && $loan['released']) {
            if ($loanOverdue > 0) {
                $parts[] = 'Your loan has <b>'.formatAmount($loanOverdue).' overdue</b>.';
            }
            if (! empty($upcoming)) {
                $next = $upcoming[0];
                $parts[] = 'The next installment of <b>'.formatAmount($next['amount']).'</b> is due on <b>'.$next['due_date']->format('j M').'</b>.';
            } elseif ($loan['next'] && $loanOverdue <= 0) {
                $parts[] = 'Your next loan installment of <b>'.formatAmount($loan['next']['amount']).'</b> is due on <b>'.$loan['next']['due_date']->format('j M Y').'</b>.';
            }
        }

        if ($loan && $loan['released'] && ($loan['overdue'] || $loanOverdue > 0)) {
            return ['label' => 'Loan overdue', 'tone' => 'warning', 'message' => implode(' ', $parts)];
        }
        if ($arrears > 0) {
            return ['label' => 'In arrears', 'tone' => 'danger', 'message' => implode(' ', $parts)];
        }

        return ['label' => 'Up to date', 'tone' => 'success', 'message' => implode(' ', $parts)];
    }

    /**
     * The next payments in date order: overdue now, loan installments in the next two months,
     * and unpaid monthly fees due this month and next.
     *
     * @return array<int, array{when: string, label: string, amount: float, overdue: bool, sort: string}>
     */
    private function upcoming(Member $member, array $summary): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $horizon = $today->addMonthsNoOverflow(2)->endOfMonth();
        $items = [];

        $arrears = (float) $summary['arrears']['total'];
        if ($arrears > 0) {
            $items[] = ['when' => 'Now', 'label' => 'Contribution arrears (fees, penalties, fines)', 'amount' => $arrears, 'overdue' => true, 'sort' => '0'];
        }
        $loanOverdue = (float) ($summary['due']['loan_overdue'] ?? 0);
        if ($loanOverdue > 0) {
            $items[] = ['when' => 'Now', 'label' => 'Loan installments and penalties (overdue)', 'amount' => $loanOverdue, 'overdue' => true, 'sort' => '0'];
        }

        $loan = $summary['loan']['model'] ?? null;
        if ($loan && $loan->status === Loan::STATUS_DISBURSED) {
            $count = $loan->installments->count();
            foreach ($loan->installments->sortBy('number') as $i) {
                /** @var LoanInstallment $i */
                $date = $i->due_date->toDateString();
                if ($i->balance() > 0 && $date >= $today->toDateString() && $date <= $horizon->toDateString()) {
                    $items[] = [
                        'when' => $i->due_date->format('j M'),
                        'label' => 'Loan installment '.$i->number.($i->number === $count ? ' (last)' : ''),
                        'amount' => $i->balance(), 'overdue' => false, 'sort' => $date,
                    ];
                }
            }
        }

        MemberDue::where('member_id', $member->id)
            ->where('due_date', '>=', $today->toDateString())
            ->where('due_date', '<=', $horizon->toDateString())
            ->orderBy('due_date')->get()
            ->each(function (MemberDue $due) use (&$items) {
                if ($due->balance() > 0) {
                    $items[] = [
                        'when' => $due->due_date->format('j M'),
                        'label' => $due->period->format('F').' monthly fee',
                        'amount' => $due->balance(), 'overdue' => false, 'sort' => $due->due_date->toDateString(),
                    ];
                }
            });

        usort($items, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

        return $items;
    }

    /**
     * Penalty rules for the reminder line, from the current contribution setting and the
     * member's loan type.
     *
     * @return array{fee_percent: ?float, loan_percent: ?float}
     */
    private function rules(array $summary): array
    {
        $today = now()->toDateString();
        $setting = ContributionSetting::query()
            ->where('start_date', '<=', $today)->where('end_date', '>=', $today)
            ->orderByDesc('start_date')->first()
            ?? ContributionSetting::query()->orderByDesc('end_date')->first();

        // The member's own loan type; otherwise the active loan types' rate (all types share it today).
        $loanType = $summary['loan']['model']->loanType
            ?? \App\Models\LoanType::query()->where('is_active', true)->orderByDesc('penalty_percent')->first();

        return [
            'fee_percent' => $setting ? (float) $setting->penalty_percent : null,
            'loan_percent' => $loanType ? (float) $loanType->penalty_percent : null,
        ];
    }

    /**
     * Loans this member guarantees, with how the borrower is doing.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function guarantees(Member $member)
    {
        $today = now()->toDateString();

        return $member->guarantees()->with(['loan.member', 'loan.loanType', 'loan.installments', 'loan.penalties'])->get()
            ->filter(fn (LoanGuarantor $g) => $g->loan !== null)
            ->map(function (LoanGuarantor $g) use ($today) {
                $loan = $g->loan;
                [$label, $tone] = match (true) {
                    $g->status === LoanGuarantor::STATUS_PENDING => ['Waiting for your answer', 'warning'],
                    $g->status === LoanGuarantor::STATUS_DECLINED => ['You declined', 'secondary'],
                    $loan->status === Loan::STATUS_COMPLETED => ['Completed', 'secondary'],
                    $loan->status === Loan::STATUS_DISBURSED && ($loan->isOverdue($today) || $loan->installments->contains(fn ($i) => $i->balance() > 0 && $i->due_date->toDateString() < $today)) => ['Behind on repayments', 'danger'],
                    $loan->status === Loan::STATUS_DISBURSED => ['Repaying on time', 'success'],
                    in_array($loan->status, [Loan::STATUS_REJECTED, Loan::STATUS_CANCELLED], true) => ['Loan not given', 'secondary'],
                    default => ['Loan not yet released', 'info'],
                };

                return [
                    'borrower' => $loan->member?->name ?? '—',
                    'loan' => ($loan->loanType?->name ?? 'Loan').' · '.formatAmount($loan->effectiveAmount()),
                    'amount' => (float) $g->guaranteed_amount,
                    'label' => $label,
                    'tone' => $tone,
                ];
            })->values();
    }
}
