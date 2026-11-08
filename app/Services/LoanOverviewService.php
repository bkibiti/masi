<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Figures for the overview at the top of the Loans page.
 *
 * Inside an installment, money received counts as interest first, then principal (same rule as
 * the dashboard), so "principal back" and "interest received" match the Group status figures.
 */
class LoanOverviewService
{
    /**
     * @param  Collection<int, Loan>  $loans  loans already loaded with installments, penalties, member
     * @param  int|null  $year  only loans released in this year; null = all years
     * @return array<string, mixed>
     */
    public function build(Collection $loans, ?int $year): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $todayStr = $today->toDateString();

        $released = $loans
            ->whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED])
            ->filter(fn (Loan $l) => $year === null || ($l->disbursed_on && $l->disbursed_on->year === $year))
            ->values();

        $principalOutstanding = 0.0;
        $overduePrincipal = 0.0;
        $interestReceived = 0.0;
        $interestDue = 0.0;
        $penaltiesReceived = 0.0;
        $penaltiesUnpaid = 0.0;
        $installmentsUnpaid = 0.0;
        $overdue = collect();

        foreach ($released as $loan) {
            $loanPrincipalOut = 0.0;
            foreach ($loan->installments as $i) {
                $paid = (float) $i->amount_paid;
                $interest = (float) $i->interest;
                $principal = (float) $i->principal;
                $interestPaid = min($paid, $interest);
                $interestReceived += $interestPaid;
                $interestDue += max(0.0, $interest - $interestPaid);
                $loanPrincipalOut += max(0.0, $principal - min($principal, max(0.0, $paid - $interest)));
                $installmentsUnpaid += $i->balance();
            }
            $principalOutstanding += $loanPrincipalOut;
            $penaltiesReceived += (float) $loan->penalties->sum('amount_paid');
            $penaltiesUnpaid += (float) $loan->penalties->sum(fn ($p) => $p->balance());

            if ($loan->status === Loan::STATUS_DISBURSED && $loan->isOverdue($todayStr)) {
                $overduePrincipal += $loanPrincipalOut;
                $overdue->push([
                    'loan' => $loan,
                    'outstanding' => $loan->outstandingBalance(),
                    'since' => $loan->installments->sortBy('number')
                        ->first(fn (LoanInstallment $i) => $i->balance() > 0 && $i->due_date->toDateString() < $todayStr)?->due_date,
                ]);
            }
        }

        $releasedTotal = (float) $released->sum(fn (Loan $l) => $l->effectiveAmount());
        $repaid = $released->isEmpty() ? 0.0 : (float) Payment::query()->whereIn('loan_id', $released->pluck('id'))->sum('amount');
        $principalBack = max(0.0, $releasedTotal - $principalOutstanding);
        $stillToCollect = $installmentsUnpaid + $penaltiesUnpaid;
        $profitEarned = $interestReceived + $penaltiesReceived;
        $profitExpected = $profitEarned + $interestDue;
        $overdueTotal = (float) $overdue->sum('outstanding');

        // Installments due in the next 30 days on running loans.
        $soon = $released->where('status', Loan::STATUS_DISBURSED)
            ->flatMap(function (Loan $loan) use ($todayStr, $today) {
                $count = $loan->installments->count();

                return $loan->installments
                    ->filter(fn (LoanInstallment $i) => $i->balance() > 0
                        && $i->due_date->toDateString() >= $todayStr
                        && $i->due_date->toDateString() <= $today->addDays(30)->toDateString())
                    ->map(fn (LoanInstallment $i) => [
                        'loan' => $loan,
                        'member' => $loan->member?->name ?? '—',
                        'due_date' => $i->due_date,
                        'amount' => $i->balance(),
                        'last' => $i->number === $count,
                    ]);
            })
            ->sortBy(fn ($r) => $r['due_date']->toDateString())
            ->values();

        // Loans released per month for one year (the selected year, else this year).
        $chartYear = $year ?? $today->year;
        $byMonth = array_fill(1, 12, 0.0);
        foreach ($loans->whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED]) as $loan) {
            if ($loan->disbursed_on && $loan->disbursed_on->year === $chartYear) {
                $byMonth[$loan->disbursed_on->month] += $loan->effectiveAmount();
            }
        }
        $maxMonth = max(1.0, max($byMonth));
        $months = [];
        foreach ($byMonth as $m => $amount) {
            $months[] = [
                'label' => CarbonImmutable::create($chartYear, $m, 1)->format('M'),
                'amount' => $amount,
                'height' => (int) round($amount / $maxMonth * 100),
            ];
        }

        return [
            'released' => $releasedTotal,
            'count' => $released->count(),
            'average' => $released->count() > 0 ? $releasedTotal / $released->count() : 0.0,
            'repaid' => $repaid,
            'principal_back' => $principalBack,
            'principal_back_pct' => $releasedTotal > 0 ? (int) round($principalBack / $releasedTotal * 100) : 0,
            'principal_outstanding' => $principalOutstanding,
            'principal_on_track' => max(0.0, $principalOutstanding - $overduePrincipal),
            'principal_overdue' => $overduePrincipal,
            'interest_received' => $interestReceived,
            'interest_due' => $interestDue,
            'penalties_received' => $penaltiesReceived,
            'penalties_unpaid' => $penaltiesUnpaid,
            'still_to_collect' => $stillToCollect,
            'profit_earned' => $profitEarned,
            'profit_expected' => $profitExpected,
            'profit_pct' => $profitExpected > 0 ? (int) round($profitEarned / $profitExpected * 100) : 0,
            'return_pct' => $releasedTotal > 0 ? round($profitEarned / $releasedTotal * 100, 1) : 0.0,
            'overdue' => $overdue->sortByDesc('outstanding')->values(),
            'overdue_total' => $overdueTotal,
            'overdue_share' => $stillToCollect > 0 ? (int) round($overdueTotal / $stillToCollect * 100) : 0,
            'soon' => $soon,
            'soon_total' => (float) $soon->sum('amount'),
            'chart_year' => $chartYear,
            'months' => $months,
        ];
    }

    /**
     * "Next due" text for one loan in the list.
     *
     * @return array{text: string, overdue: bool}
     */
    public function nextDue(Loan $loan): array
    {
        if ($loan->status === Loan::STATUS_COMPLETED) {
            return ['text' => 'Paid off', 'overdue' => false];
        }
        if ($loan->status !== Loan::STATUS_DISBURSED) {
            return ['text' => '—', 'overdue' => false];
        }

        $today = now()->toDateString();
        $overdue = (float) $loan->installments->filter(fn ($i) => $i->due_date->toDateString() < $today)->sum(fn ($i) => $i->balance())
            + (float) $loan->penalties->sum(fn ($p) => $p->balance());
        if ($overdue > 0) {
            return ['text' => formatAmount($overdue).' overdue', 'overdue' => true];
        }

        $next = $loan->installments->sortBy('number')->first(fn ($i) => $i->balance() > 0);

        return $next
            ? ['text' => $next->due_date->format('j M').' · '.formatAmount($next->balance()), 'overdue' => false]
            : ['text' => '—', 'overdue' => false];
    }

    /** Share of the installments already paid, 0-100. */
    public function repaidPercent(Loan $loan): int
    {
        $total = (float) $loan->installments->sum('amount');

        return $total > 0 ? (int) min(100, round((float) $loan->installments->sum('amount_paid') / $total * 100)) : 0;
    }
}
