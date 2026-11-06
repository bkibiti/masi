<?php

namespace App\Http\Controllers;

use App\Models\FineType;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPenalty;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\ContributionLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(): View
    {
        $members = Member::query()->with('user')->orderBy('name')->get();

        return view('members.index', compact('members'));
    }

    public function show(Request $request, Member $member, ContributionLedger $ledger): View
    {
        $member->load('user');
        $year = (int) $request->query('year', now()->year);
        $statement = $ledger->statementForMember($member, $year);
        $payments = $member->payments()->with(['recorder', 'paymentType', 'paymentMethod'])
            ->reorder()->orderByDesc('paid_on')->orderByDesc('id')->get(); // newest first (the relation itself sorts oldest first)
        $years = range(now()->year - 2, now()->year + 1);
        $loans = $member->loans()->with('loanType')->get();
        $guarantees = $member->guarantees()->with('loan.member')->get();
        $linkableUsers = $member->user_id === null
            ? User::whereDoesntHave('member')->orderBy('name')->get()
            : collect();
        $summary = $this->summary($member, $payments, $statement);

        // Penalties tab: every automatic penalty and manual fine charged (all years), merged into
        // one list, newest first, plus loan penalties.
        $feeChargeRecords = $ledger->feeCharges($member);
        $loanPenaltyRecords = LoanPenalty::query()->whereIn('loan_id', $loans->pluck('id'))
            ->with('loan.loanType')->orderByDesc('charged_on')->get();
        $fineTypes = FineType::where('is_active', true)->orderBy('name')->get();

        return view('members.show', compact(
            'member', 'statement', 'year', 'years', 'payments', 'loans', 'guarantees', 'linkableUsers',
            'summary', 'feeChargeRecords', 'loanPenaltyRecords', 'fineTypes'
        ));
    }

    /**
     * Figures for the four summary cards: total paid, current loan, arrears (monthly fees and
     * penalties past due only) and the amount due by the end of the current month.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    private function summary(Member $member, $payments, array $statement): array
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
                'total' => $feesPastDue + $penaltiesUnpaid + $finesUnpaid + $currentMonthFee + $loanDueByMonthEnd,
                'next_after' => $nextAfter,
            ],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $member = Member::create([
            ...$data,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('alert-success', 'Member added successfully!');

        return redirect()->route('members.show', $member);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);

        $member->update([...$data, 'updated_by' => auth()->id()]);

        session()->flash('alert-success', 'Member updated successfully!');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Member $member = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'phone' => ['required', 'regex:/^255[0-9]{9}$/', Rule::unique('members', 'phone')->ignore($member?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member?->id)],
        ], [
            'phone.regex' => 'Mobile must be 12 digits starting with 255, e.g. 255745777602.',
            'phone.unique' => 'This mobile number is already registered to another member.',
            'email.unique' => 'This email is already registered to another member.',
        ]);
    }
}
