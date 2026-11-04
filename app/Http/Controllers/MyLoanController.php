<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Member;
use App\Services\LoanCalculator;
use App\Services\LoanEligibility;
use App\Services\LoanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My Loans" (spec section 3): apply, cancel own pending application, and view the loan
 * detail page. Always scoped to auth()->user()->member, never a route parameter.
 */
class MyLoanController extends Controller
{
    public function index(Request $request, LoanEligibility $eligibility): View
    {
        $member = $request->user()->member;
        $loans = $member->loans()->with(['loanType', 'installments', 'penalties'])->get();

        return view('account.loans.index', [
            'loans' => $loans,
            'hasActiveLoan' => $eligibility->hasActiveLoan($member),
        ]);
    }

    public function create(Request $request, LoanEligibility $eligibility): View
    {
        $member = $request->user()->member;
        $loanTypes = LoanType::where('is_active', true)->orderBy('name')->get();
        $possibleGuarantors = Member::where('id', '!=', $member->id)->orderBy('name')->get();

        return view('account.loans.create', [
            'member' => $member,
            'loanTypes' => $loanTypes,
            'possibleGuarantors' => $possibleGuarantors,
            'hasActiveLoan' => $eligibility->hasActiveLoan($member),
        ]);
    }

    public function store(Request $request, LoanService $service): RedirectResponse
    {
        $member = $request->user()->member;

        $data = $request->validate([
            'loan_type_id' => 'required|exists:loan_types,id',
            'requested_amount' => 'required|numeric|min:1',
            'requested_duration_months' => 'required|integer|min:1',
            'purpose' => 'nullable|string|max:1000',
            'guarantors' => 'nullable|array',
            'guarantors.*.guarantor_member_id' => ['required_with:guarantors', 'exists:members,id', Rule::notIn([$member->id])],
            'guarantors.*.guaranteed_amount' => 'required_with:guarantors|numeric|min:1',
        ]);

        $loanType = LoanType::findOrFail($data['loan_type_id']);

        $loan = $service->apply(
            $member,
            $loanType,
            (float) $data['requested_amount'],
            (int) $data['requested_duration_months'],
            $data['purpose'] ?? null,
            $data['guarantors'] ?? [],
            $request->user(),
        );

        session()->flash('alert-success', 'Loan application submitted.');

        return redirect()->route('account.loans.show', $loan);
    }

    public function show(Request $request, Loan $loan): View
    {
        abort_unless((int) $loan->member_id === (int) $request->user()->member->id, 403);

        $loan->load(['loanType', 'guarantors.guarantorMember', 'installments', 'penalties', 'statusHistory', 'payments.paymentMethod']);

        return view('account.loans.show', compact('loan'));
    }

    public function cancel(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless((int) $loan->member_id === (int) $request->user()->member->id, 403);

        $service->cancel($loan, $request->user());

        session()->flash('alert-success', 'Loan application cancelled.');

        return redirect()->route('account.loans.index');
    }

    /**
     * Eligibility + repayment preview for the apply form, scoped to the logged-in member
     * (the staff equivalent, LoanController::preview, requires "Manage Loans").
     */
    public function preview(Request $request, LoanEligibility $eligibility, LoanCalculator $calculator): JsonResponse
    {
        $member = $request->user()->member;

        $data = $request->validate([
            'loan_type_id' => 'required|exists:loan_types,id',
            'amount' => 'required|numeric|min:1',
            'duration_months' => 'required|integer|min:1',
        ]);

        $loanType = LoanType::findOrFail($data['loan_type_id']);
        $amount = (float) $data['amount'];
        $months = (int) $data['duration_months'];

        $summary = $eligibility->summary($member, $amount);
        $withinLimits = $eligibility->withinLoanTypeLimits($loanType, $amount, $months);
        $rows = $withinLimits
            ? $calculator->installments($amount, (float) $loanType->interest_rate, $months, $loanType->interest_method, CarbonImmutable::now())
            : [];

        return response()->json([
            ...$summary,
            'within_limits' => $withinLimits,
            'has_active_loan' => $eligibility->hasActiveLoan($member),
            'installments' => array_map(fn ($row) => [
                'number' => $row['number'],
                'due_date' => $row['due_date']->toDateString(),
                'principal' => (float) $row['principal'],
                'interest' => (float) $row['interest'],
                'amount' => (float) $row['amount'],
            ], $rows),
            'total_interest' => array_sum(array_column($rows, 'interest')),
            'total_payable' => array_sum(array_column($rows, 'amount')),
        ]);
    }
}
