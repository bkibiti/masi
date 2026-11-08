<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\OtherIncome;
use App\Models\Payment;
use App\Models\PaymentType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only figures for the "Group status" dashboard.
 *
 * Money rules (cash basis):
 *  - Cash available = all payments received + other income - loan amounts released - expenses.
 *  - Cash outside   = loan principal not yet repaid (released loans only).
 *  - Group fund     = cash available + cash outside.
 *  - Loan profit    = interest + loan penalties actually received. Inside an installment,
 *                     money received counts as interest first, then principal.
 *  - Expected loan profit = profit earned + interest still scheduled on unpaid installments.
 */
class GroupStatusService
{
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /** @return array<string, mixed> */
    public function build(int $year): array
    {
        $today = $this->today();
        $loans = Loan::query()
            ->with(['member', 'loanType', 'installments', 'penalties'])
            ->get();
        $released = $loans->whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED]);

        $loanFigures = $this->loanFigures($released);

        return [
            'money' => $this->money($released, $loanFigures),
            'profit' => $this->profit($loanFigures),
            'contributions' => $this->contributions($year, $today),
            'loans' => $this->loans($loans, $released, $loanFigures, $year, $today),
            'fines' => $this->fines($year),
            'expenses' => $this->expenses($year),
        ];
    }

    /**
     * Principal / interest / penalty position across released loans.
     *
     * @return array<string, float>
     */
    private function loanFigures(Collection $released): array
    {
        $principalOutstanding = 0.0;
        $interestReceived = 0.0;
        $interestDue = 0.0;
        $penaltiesReceived = 0.0;
        $penaltiesUnpaid = 0.0;
        $installmentsUnpaid = 0.0;

        foreach ($released as $loan) {
            foreach ($loan->installments as $installment) {
                $paid = (float) $installment->amount_paid;
                $interest = (float) $installment->interest;
                $principal = (float) $installment->principal;

                $interestPaid = min($paid, $interest);
                $principalPaid = min($principal, max(0.0, $paid - $interest));

                $interestReceived += $interestPaid;
                $interestDue += max(0.0, $interest - $interestPaid);
                $principalOutstanding += max(0.0, $principal - $principalPaid);
                $installmentsUnpaid += $installment->balance();
            }

            foreach ($loan->penalties as $penalty) {
                $penaltiesReceived += (float) $penalty->amount_paid;
                $penaltiesUnpaid += $penalty->balance();
            }
        }

        return [
            'principal_outstanding' => $principalOutstanding,
            'interest_received' => $interestReceived,
            'interest_due' => $interestDue,
            'penalties_received' => $penaltiesReceived,
            'penalties_unpaid' => $penaltiesUnpaid,
            'outstanding' => $installmentsUnpaid + $penaltiesUnpaid,
        ];
    }

    /** @return array<string, mixed> */
    private function money(Collection $released, array $loanFigures): array
    {
        $byRole = Payment::query()
            ->join('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
            ->whereNull('payments.deleted_at')
            ->selectRaw('payment_types.ledger_role as role, SUM(payments.amount) as total')
            ->groupBy('payment_types.ledger_role')
            ->pluck('total', 'role')
            ->map(fn ($v) => (float) $v);

        $received = (float) $byRole->sum();
        $releasedTotal = (float) $released->sum(fn (Loan $l) => $l->effectiveAmount());
        $expenses = (float) Expense::query()->sum('amount');
        $otherIncome = (float) OtherIncome::query()->sum('amount');

        $cash = $received + $otherIncome - $releasedTotal - $expenses;
        $outside = $loanFigures['principal_outstanding'];
        $fund = $cash + $outside;

        $fees = (float) ($byRole[PaymentType::ROLE_MONTHLY_FEE] ?? 0);
        $fines = (float) ($byRole[PaymentType::ROLE_PENALTY] ?? 0);
        $interest = $loanFigures['interest_received'];
        $loanPenalties = $loanFigures['penalties_received'];

        // Anything not explained by the lines above is loan income: mostly the extra principal from
        // rounding installments up (members repay slightly more than they borrowed), plus any loan
        // overpayment or "none"-type payment. It is shown as part of the Loan interest line.
        $other = round($fund - ($fees + $fines + $interest + $loanPenalties + $otherIncome - $expenses), 2);
        $interest += $other;
        $other = 0.0;

        return [
            'fund' => $fund,
            'cash' => $cash,
            'outside' => $outside,
            'outside_loans' => $released->where('status', Loan::STATUS_DISBURSED)->count(),
            'fees' => $fees,
            'fines' => $fines,
            'interest' => $interest,
            'loan_penalties' => $loanPenalties,
            'other' => $other,
            'other_income' => $otherIncome,
            'expenses' => $expenses,
            'cash_percent' => $fund > 0 ? (int) round(max(0, $cash) / $fund * 100) : 0,
        ];
    }

    /** @return array<string, float> */
    private function profit(array $loanFigures): array
    {
        $earned = $loanFigures['interest_received'] + $loanFigures['penalties_received'];
        $expected = $earned + $loanFigures['interest_due'];

        return [
            'interest' => $loanFigures['interest_received'],
            'penalties' => $loanFigures['penalties_received'],
            'interest_due' => $loanFigures['interest_due'],
            'earned' => $earned,
            'expected' => $expected,
            'penalties_unpaid' => $loanFigures['penalties_unpaid'],
            'interest_pct' => $expected > 0 ? round($loanFigures['interest_received'] / $expected * 100, 1) : 0,
            'penalties_pct' => $expected > 0 ? round($loanFigures['penalties_received'] / $expected * 100, 1) : 0,
            'due_pct' => $expected > 0 ? round($loanFigures['interest_due'] / $expected * 100, 1) : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function contributions(int $year, CarbonImmutable $today): array
    {
        $isCurrentYear = $year === $today->year;
        $lastPeriod = $isCurrentYear ? $today->startOfMonth() : CarbonImmutable::create($year, 12, 1);

        $totals = MemberDue::query()
            ->whereYear('period', $year)
            ->where('period', '<=', $lastPeriod->toDateString())
            ->selectRaw('COALESCE(SUM(amount), 0) as expected, COALESCE(SUM(amount_paid), 0) as collected')
            ->first();

        $expected = (float) $totals->expected;
        $collected = (float) $totals->collected;

        // Arrears per member (as at today): past-due fees + unpaid contribution penalties + unpaid fines.
        $todayStr = $today->toDateString();
        $feeArrears = MemberDue::query()->where('due_date', '<', $todayStr)
            ->selectRaw('member_id, SUM(amount - amount_paid) as total')->groupBy('member_id')->pluck('total', 'member_id');
        $penaltyArrears = MemberPenalty::query()
            ->selectRaw('member_id, SUM(amount - amount_paid) as total')->groupBy('member_id')->pluck('total', 'member_id');
        $fineArrears = MemberFine::query()
            ->selectRaw('member_id, SUM(amount - amount_paid) as total')->groupBy('member_id')->pluck('total', 'member_id');

        $memberIds = Member::query()->pluck('id');
        $inArrears = 0;
        $arrearsTotal = 0.0;
        foreach ($memberIds as $id) {
            $owed = (float) ($feeArrears[$id] ?? 0) + (float) ($penaltyArrears[$id] ?? 0) + (float) ($fineArrears[$id] ?? 0);
            if ($owed > 0.5) {
                $inArrears++;
                $arrearsTotal += $owed;
            }
        }

        $rows = MemberDue::query()->whereYear('period', $year)
            ->selectRaw('period, SUM(amount) as expected, SUM(amount_paid) as collected')
            ->groupBy('period')->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->period)->month);
        $max = max(1.0, (float) $rows->max('expected'));
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $rows->get($m);
            $months[] = [
                'label' => CarbonImmutable::create($year, $m, 1)->format('M'),
                'expected' => (float) ($row->expected ?? 0),
                'collected' => (float) ($row->collected ?? 0),
                'height' => $row ? (int) round((float) $row->collected / $max * 100) : 0,
                'has_dues' => $row !== null,
                'is_current' => $isCurrentYear && $m === $today->month,
            ];
        }

        $current = $isCurrentYear ? $rows->get($today->month) : null;

        return [
            'expected' => $expected,
            'collected' => $collected,
            'percent' => $expected > 0 ? (int) min(100, round($collected / $expected * 100)) : 0,
            'members' => $memberIds->count(),
            'up_to_date' => $memberIds->count() - $inArrears,
            'in_arrears' => $inArrears,
            'arrears_total' => $arrearsTotal,
            'months' => $months,
            'current_label' => $isCurrentYear ? $today->format('F') : null,
            'current_collected' => (float) ($current->collected ?? 0),
            'current_expected' => (float) ($current->expected ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function loans(Collection $loans, Collection $released, array $loanFigures, int $year, CarbonImmutable $today): array
    {
        $todayStr = $today->toDateString();
        $overdue = $released->filter(fn (Loan $l) => $l->isOverdue($todayStr));

        $counts = [
            'Pending' => $loans->whereIn('status', [Loan::STATUS_PENDING_GUARANTORS, Loan::STATUS_PENDING_APPROVAL])->count(),
            'Approved' => $loans->where('status', Loan::STATUS_APPROVED)->count(),
            'Active' => $released->where('status', Loan::STATUS_DISBURSED)->count() - $overdue->count(),
            'Overdue' => $overdue->count(),
            'Completed' => $released->where('status', Loan::STATUS_COMPLETED)->count(),
        ];

        $soon = LoanInstallment::query()
            ->whereHas('loan', fn ($q) => $q->where('status', Loan::STATUS_DISBURSED))
            ->whereBetween('due_date', [$todayStr, $today->addDays(30)->toDateString()])
            ->get()
            ->filter(fn (LoanInstallment $i) => $i->balance() > 0);

        return [
            'counts' => $counts,
            'released_in_year' => (float) $released->filter(fn (Loan $l) => $l->disbursed_on && $l->disbursed_on->year === $year)
                ->sum(fn (Loan $l) => $l->effectiveAmount()),
            'repaid' => (float) Payment::query()->loanRelevant()->sum('amount'),
            'outstanding' => $loanFigures['outstanding'],
            'principal' => $loanFigures['principal_outstanding'],
            'interest_due' => $loanFigures['interest_due'],
            'penalties_unpaid' => $loanFigures['penalties_unpaid'],
            'overdue' => $overdue->map(fn (Loan $l) => [
                'id' => $l->id,
                'member' => $l->member->name,
                'outstanding' => $l->outstandingBalance(),
                'since' => $l->installments->first(fn ($i) => $i->balance() > 0 && $i->due_date->toDateString() < $todayStr)?->due_date,
            ])->sortByDesc('outstanding')->values(),
            'overdue_total' => (float) $overdue->sum(fn (Loan $l) => $l->outstandingBalance()),
            'soon_count' => $soon->count(),
            'soon_total' => (float) $soon->sum(fn (LoanInstallment $i) => $i->balance()),
        ];
    }

    /** @return array<string, mixed> */
    private function fines(int $year): array
    {
        $rows = [];

        $penalties = MemberPenalty::query()->whereYear('charged_on', $year)
            ->selectRaw('COALESCE(SUM(amount), 0) as charged, COALESCE(SUM(amount_paid), 0) as paid')->first();
        if ((float) $penalties->charged > 0) {
            $rows[] = ['name' => 'Late contribution penalties', 'charged' => (float) $penalties->charged, 'paid' => (float) $penalties->paid];
        }

        $manual = MemberFine::query()->with('fineType')->whereYear('fined_on', $year)->get()
            ->groupBy(fn (MemberFine $f) => $f->fineType?->name ?? 'Other fines');
        foreach ($manual as $name => $group) {
            $rows[] = ['name' => $name, 'charged' => (float) $group->sum('amount'), 'paid' => (float) $group->sum('amount_paid')];
        }

        $rows = collect($rows)->map(fn ($r) => $r + ['unpaid' => max(0.0, $r['charged'] - $r['paid'])]);

        return [
            'rows' => $rows,
            'charged' => (float) $rows->sum('charged'),
            'paid' => (float) $rows->sum('paid'),
            'unpaid' => (float) $rows->sum('unpaid'),
        ];
    }

    /** @return array<string, mixed> */
    private function expenses(int $year): array
    {
        $items = Expense::query()->with('category')->whereYear('created_at', $year)->get();
        $total = (float) $items->sum('amount');

        $byCategory = $items->groupBy(fn ($e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn ($g, $name) => ['name' => $name, 'total' => (float) $g->sum('amount')])
            ->sortByDesc('total')->values()
            ->map(fn ($c) => $c + ['percent' => $total > 0 ? (int) round($c['total'] / $total * 100) : 0]);

        $latest = $items->sortByDesc('created_at')->take(3)->map(fn ($e) => [
            'date' => CarbonImmutable::parse($e->created_at)->setTimezone(self::TIMEZONE),
            'description' => $e->expense_description ?: ($e->category?->name ?? 'Expense'),
            'amount' => (float) $e->amount,
        ])->values();

        return ['total' => $total, 'categories' => $byCategory, 'latest' => $latest];
    }
}
