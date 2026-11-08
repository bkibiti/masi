<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Services\LoanCalculator;
use App\Services\LoanEligibility;
use App\Services\LoanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanController extends Controller
{
    /** @var array<string, string> */
    private const TABS = [
        'pending_guarantors' => 'Pending guarantors',
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'active' => 'Active',
        'overdue' => 'Overdue',
        'completed' => 'Completed',
        'rejected_cancelled' => 'Rejected / Cancelled',
    ];

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending_approval');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'pending_approval';

        $memberId = $request->query('member_id');
        $loanTypeId = $request->query('loan_type_id');

        $loans = Loan::query()
            ->with(['member', 'loanType', 'installments', 'penalties'])
            ->when($memberId, fn ($q) => $q->where('member_id', $memberId))
            ->when($loanTypeId, fn ($q) => $q->where('loan_type_id', $loanTypeId))
            ->orderByDesc('id')
            ->get();

        $today = now()->toDateString();
        // One separate collection per tab. (array_fill_keys(..., collect()) would put the SAME
        // collection object under every key, so each loan showed up, and was counted, in every tab.)
        $grouped = array_map(fn () => collect(), self::TABS);

        foreach ($loans as $loan) {
            $grouped[$this->tabFor($loan, $today)]->push($loan);
        }

        $rows = $grouped[$tab];

        // Overview at the top of the page (respects the member / loan type filters and the year).
        $overviewYear = $request->query('year', 'all');
        $overviewYear = $overviewYear === 'all' ? null : (int) $overviewYear;
        $overviewService = app(\App\Services\LoanOverviewService::class);
        $overview = $overviewService->build($loans, $overviewYear);
        $overviewYears = $loans->pluck('disbursed_on')->filter()->map(fn ($d) => $d->year)->unique()->sortDesc()->values();

        $counts = array_map(fn ($c) => $c->count(), $grouped);

        $members = Member::orderBy('name')->get();
        $loanTypes = LoanType::orderBy('name')->get();

        return view('loans.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'rows' => $rows,
            'counts' => $counts,
            'overview' => $overview,
            'overviewService' => $overviewService,
            'overviewYear' => $overviewYear,
            'overviewYears' => $overviewYears,
            'members' => $members,
            'loanTypes' => $loanTypes,
            'memberId' => $memberId,
            'loanTypeId' => $loanTypeId,
        ]);
    }

    public function create(): View
    {
        $members = Member::orderBy('name')->get();
        $loanTypes = LoanType::where('is_active', true)->orderBy('name')->get();

        return view('loans.create', compact('members', 'loanTypes'));
    }

    public function store(Request $request, LoanService $service): RedirectResponse
    {
        $data = $this->validatedApplication($request);

        $member = Member::findOrFail($data['member_id']);
        $loanType = LoanType::findOrFail($data['loan_type_id']);

        $loan = $service->apply(
            $member,
            $loanType,
            (float) $data['requested_amount'],
            (int) $data['requested_duration_months'],
            $data['purpose'] ?? null,
            $data['guarantors'] ?? [],
            $request->user(),
            $data['applied_on'] ?? null,
        );

        session()->flash('alert-success', 'Loan application recorded.');

        return redirect()->route('loans.show', $loan);
    }

    public function show(Loan $loan): View
    {
        $loan->load([
            'member', 'loanType',
            'guarantors.guarantorMember', 'guarantors.respondedBy',
            'installments', 'penalties',
            'statusHistory.changedBy',
            'payments.paymentMethod', 'payments.recorder',
            'appliedBy', 'approvedBy', 'rejectedBy', 'cancelledBy', 'disbursedBy', 'disbursementPaymentMethod',
        ]);

        $members = Member::where('id', '!=', $loan->member_id)->orderBy('name')->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name')->get();

        return view('loans.show', compact('loan', 'members', 'paymentMethods'));
    }

    /**
     * Eligibility + repayment preview for the apply form, before the loan is saved.
     */
    public function preview(Request $request, LoanEligibility $eligibility, LoanCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'loan_type_id' => 'required|exists:loan_types,id',
            'amount' => 'required|numeric|min:1',
            'duration_months' => 'required|integer|min:1',
        ]);

        $member = Member::findOrFail($data['member_id']);
        $loanType = LoanType::findOrFail($data['loan_type_id']);
        $amount = (float) $data['amount'];
        $months = (int) $data['duration_months'];

        $summary = $eligibility->summary($member, $amount);
        $withinLimits = $eligibility->withinLoanTypeLimits($loanType, $amount, $months);
        $rows = $withinLimits
            ? $calculator->installments($amount, (float) $loanType->interest_rate, $months, $loanType->interest_method, CarbonImmutable::now(), (int) $loanType->installment_rounding, (int) $loanType->interest_rounding)
            : [];

        return response()->json([
            ...$summary,
            'within_limits' => $withinLimits,
            'has_active_loan' => $eligibility->hasActiveLoan($member),
            'installments' => $this->serializeInstallments($rows),
            'total_interest' => array_sum(array_column($rows, 'interest')),
            'total_payable' => array_sum(array_column($rows, 'amount')),
        ]);
    }

    public function storeGuarantor(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'guarantor_member_id' => 'required|exists:members,id',
            'guaranteed_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $service->addGuarantor($loan, (int) $data['guarantor_member_id'], (float) $data['guaranteed_amount'], $data['notes'] ?? null);

        session()->flash('alert-success', 'Guarantor added.');

        return back();
    }

    public function destroyGuarantor(Loan $loan, LoanGuarantor $guarantor, LoanService $service): RedirectResponse
    {
        $service->removeGuarantor($loan, $guarantor);

        session()->flash('alert-success', 'Guarantor removed.');

        return back();
    }

    public function respondGuarantor(Request $request, Loan $loan, LoanGuarantor $guarantor, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'response' => ['required', Rule::in([LoanGuarantor::STATUS_ACCEPTED, LoanGuarantor::STATUS_DECLINED])],
            'notes' => 'nullable|string|max:500',
        ]);

        $service->recordGuarantorResponse($guarantor, $data['response'], $request->user(), $data['notes'] ?? null);

        session()->flash('alert-success', 'Guarantor response recorded.');

        return back();
    }

    public function approve(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'approved_amount' => 'nullable|numeric|min:1',
            'approved_duration_months' => 'nullable|integer|min:1',
            'approved_on' => 'nullable|date|before_or_equal:today',
        ]);

        $service->approve(
            $loan,
            $request->user(),
            isset($data['approved_amount']) ? (float) $data['approved_amount'] : null,
            $data['approved_duration_months'] ?? null,
            $data['approved_on'] ?? null,
        );

        session()->flash('alert-success', 'Loan approved.');

        return back();
    }

    public function reject(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $service->reject($loan, $request->user(), $data['rejection_reason']);

        session()->flash('alert-success', 'Loan rejected.');

        return back();
    }

    public function cancel(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $service->cancel($loan, $request->user());

        session()->flash('alert-success', 'Loan cancelled.');

        return back();
    }

    public function disburse(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'disbursed_on' => 'required|date|before_or_equal:today',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'reference' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        $service->disburse(
            $loan,
            $request->user(),
            $data['disbursed_on'],
            (int) $data['payment_method_id'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        session()->flash('alert-success', 'Loan disbursed.');

        return back();
    }

    public function updateDisbursementDate(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'disbursed_on' => 'required|date|before_or_equal:today',
        ]);

        $service->changeDisbursementDate($loan, $request->user(), $data['disbursed_on']);

        session()->flash('alert-success', 'Release date corrected. Due dates and penalties were recalculated.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedApplication(Request $request): array
    {
        return $request->validate([
            'member_id' => 'required|exists:members,id',
            'loan_type_id' => 'required|exists:loan_types,id',
            'requested_amount' => 'required|numeric|min:1',
            'requested_duration_months' => 'required|integer|min:1',
            'purpose' => 'nullable|string|max:1000',
            'applied_on' => 'nullable|date|before_or_equal:today',
            'guarantors' => 'nullable|array',
            'guarantors.*.guarantor_member_id' => 'required_with:guarantors|exists:members,id',
            'guarantors.*.guaranteed_amount' => 'required_with:guarantors|numeric|min:1',
        ]);
    }

    private function tabFor(Loan $loan, string $today): string
    {
        return match (true) {
            $loan->status === Loan::STATUS_PENDING_GUARANTORS => 'pending_guarantors',
            $loan->status === Loan::STATUS_PENDING_APPROVAL => 'pending_approval',
            $loan->status === Loan::STATUS_APPROVED => 'approved',
            $loan->status === Loan::STATUS_DISBURSED && $loan->isOverdue($today) => 'overdue',
            $loan->status === Loan::STATUS_DISBURSED => 'active',
            $loan->status === Loan::STATUS_COMPLETED => 'completed',
            default => 'rejected_cancelled',
        };
    }

    /**
     * @param  array<int, array{number: int, due_date: CarbonImmutable, principal: float, interest: float, amount: float}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function serializeInstallments(array $rows): array
    {
        return array_map(fn ($row) => [
            'number' => $row['number'],
            'due_date' => $row['due_date']->toDateString(),
            'principal' => (float) $row['principal'],
            'interest' => (float) $row['interest'],
            'amount' => (float) $row['amount'],
        ], $rows);
    }
}
