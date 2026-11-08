<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPenalty;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\PaymentType;
use Carbon\CarbonImmutable;

/**
 * The four summary cards for one member (total contributed, current loan, arrears, amount due
 * by month end). Shared by the admin member page and the member's own "My Statement" page so
 * both always show the same figures.
 */
class MemberSummaryService
{
    /**
     * @param  \Illuminate\Support\Collection  $payments  all the member's payments (any order)
     * @param  array<string, mixed>  $statement  ContributionLedger::statementForMember() result
     * @return array<string, mixed>
     */
    public function build(Member $member, $payments, array $statement): array
    {
        $today = CarbonImmutable::now()->toDateString();
        $monthEnd = CarbonImmutable::now()->endOfMonth()->toDateString();

        // 1. Total paid, by payment type role.
        $byRole = fn (string $role) => (float) $payments->filter(fn ($p) => $p->paymentType?->ledger_role === $role)->sum('amount');
        $paid = [
            'total' => (float) $payments->sum('amount'),
            'monthly_fees' => $byRole(PaymentType::ROLE_MONTHLY_FEE),
            'fines' => $byRole(PaymentType::ROLE_PENALTY),
            'loans' => $byRole(PaymentType::ROLE_LOAN),
            'other' => $byRole(PaymentType::ROLE_NONE),
            'last_paid_on' => $payments->max('paid_on'),
        ];
        // What the member has put into the group: fees + fines/penalties (+ other). Loan
        // repayments are money being returned, so they are shown separately, not in this total.
        $paid['contributed'] = $paid['monthly_fees'] + $paid['fines'] + $paid['other'];

        // 2. Arrears: monthly fees past their due date + unpaid contribution penalties + unpaid
        // manual fines (always due immediately).
        $dues = $member->dues()->with('penalties')->get();
        $feesPastDue = (float) $dues->filter(fn (MemberDue $d) => $d->due_date->toDateString() < $today)->sum(fn (MemberDue $d) => $d->balance());
        $penaltiesUnpaid = (float) $dues->flatMap(fn (MemberDue $d) => $d->penalties)->sum(fn (MemberPenalty $p) => $p->balance());
        $finesUnpaid = (float) $member->fines()->get()->sum(fn (MemberFine $f) => $f->balance());
        $currentMonthFee = (float) $dues->filter(fn (MemberDue $d) => $d->due_date->toDateString() >= $today && $d->due_date->toDateString() <= $monthEnd)->sum(fn (MemberDue $d) => $d->balance());

        // 3. Current loan: the released (active) loan, else one still in the approval workflow.
        $loan = $member->loans()->with(['loanType', 'installments', 'penalties'])
            ->whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_APPROVED, Loan::STATUS_PENDING_APPROVAL, Loan::STATUS_PENDING_GUARANTORS])
            ->orderByRaw("status = 'disbursed' desc")->latest('id')->first();

        $loanSummary = null;
        $loanDueByMonthEnd = 0.0;
        $loanOverdue = 0.0;
        $loanUpcoming = [];
        $nextLoanAfterMonthEnd = null;

        if ($loan) {
            $installments = $loan->installments->sortBy('number');
            $loanPenalties = (float) $loan->penalties->sum(fn (LoanPenalty $p) => $p->balance());
            $totalPayable = (float) $installments->sum('amount');
            $unpaid = $installments->filter(fn (LoanInstallment $i) => $i->balance() > 0);
            $next = $unpaid->first();

            $loanDueNow = (float) $installments->filter(fn (LoanInstallment $i) => $i->due_date->toDateString() <= $today)->sum(fn (LoanInstallment $i) => $i->balance()) + $loanPenalties;
            $loanDueByMonthEnd = (float) $installments->filter(fn (LoanInstallment $i) => $i->due_date->toDateString() <= $monthEnd)->sum(fn (LoanInstallment $i) => $i->balance()) + $loanPenalties;
            $nextLoanAfterMonthEnd = $unpaid->first(fn (LoanInstallment $i) => $i->due_date->toDateString() > $monthEnd);

            // Split for the "Amount due" card: what is already overdue (past installments +
            // unpaid loan penalties) and each installment falling due between today and month end,
            // shown with its own due date.
            $loanOverdue = (float) $unpaid->filter(fn (LoanInstallment $i) => $i->due_date->toDateString() < $today)->sum(fn (LoanInstallment $i) => $i->balance()) + $loanPenalties;
            $loanUpcoming = $unpaid
                ->filter(fn (LoanInstallment $i) => $i->due_date->toDateString() >= $today && $i->due_date->toDateString() <= $monthEnd)
                ->map(fn (LoanInstallment $i) => ['amount' => $i->balance(), 'due_date' => $i->due_date])
                ->values()->all();

            $loanSummary = [
                'model' => $loan,
                'released' => $loan->status === Loan::STATUS_DISBURSED,
                'overdue' => $loan->isOverdue($today),
                'outstanding' => $loan->outstandingBalance(),
                'amount' => $loan->effectiveAmount(),
                'progress' => $totalPayable > 0 ? (int) round(($totalPayable - (float) $unpaid->sum(fn ($i) => $i->balance())) / $totalPayable * 100) : 0,
                'paid_count' => $installments->filter(fn (LoanInstallment $i) => $i->isFullyPaid())->count(),
                'installment_count' => $installments->count(),
                'next' => $next ? ['amount' => $next->balance(), 'due_date' => $next->due_date] : null,
                'due_now' => $loanDueNow,
            ];
        }

        // 4. Amount due by the end of this month: arrears + this month's fee + loan due by then.
        $nextAfter = null;
        if ($nextLoanAfterMonthEnd) {
            $nextAfter = ['label' => 'Loan installment', 'amount' => $nextLoanAfterMonthEnd->balance(), 'due_date' => $nextLoanAfterMonthEnd->due_date];
        }

        return [
            'paid' => $paid,
            'arrears' => [
                'total' => $feesPastDue + $penaltiesUnpaid + $finesUnpaid,
                'fees' => $feesPastDue,
                'penalties' => $penaltiesUnpaid,
                'fines' => $finesUnpaid,
                'credit' => (float) ($statement['credit'] ?? 0),
            ],
            'loan' => $loanSummary,
            'due' => [
                'month_end' => CarbonImmutable::now()->endOfMonth(),
                'arrears' => $feesPastDue + $penaltiesUnpaid + $finesUnpaid,
                'current_fee' => $currentMonthFee,
                'loan' => $loanDueByMonthEnd,
                'loan_overdue' => $loanOverdue,
                'loan_upcoming' => $loanUpcoming,
                'total' => $feesPastDue + $penaltiesUnpaid + $finesUnpaid + $currentMonthFee + $loanDueByMonthEnd,
                'next_after' => $nextAfter,
            ],
        ];
    }
}
