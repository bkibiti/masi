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
use App\Services\MemberSummaryService;
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

        // Member switcher: every member A-Z, plus the previous / next member in that order.
        $allMembers = Member::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'phone']);
        $position = $allMembers->search(fn (Member $m) => $m->id === $member->id);
        $previousMember = $position > 0 ? $allMembers[$position - 1] : null;
        $nextMember = $position !== false && $position < $allMembers->count() - 1 ? $allMembers[$position + 1] : null;
        $memberPosition = $position === false ? null : $position + 1;

        return view('members.show', compact(
            'member', 'statement', 'year', 'years', 'payments', 'loans', 'guarantees', 'linkableUsers',
            'summary', 'feeChargeRecords', 'loanPenaltyRecords', 'fineTypes',
            'allMembers', 'previousMember', 'nextMember', 'memberPosition'
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
        return app(MemberSummaryService::class)->build($member, $payments, $statement);
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
