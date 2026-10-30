<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\ContributionLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(): View
    {
        $members = Member::query()->orderBy('name')->get();

        return view('members.index', compact('members'));
    }

    public function show(Request $request, Member $member, ContributionLedger $ledger): View
    {
        $year = (int) $request->query('year', now()->year);
        $statement = $ledger->statementForMember($member, $year);
        $payments = $member->payments()->with(['recorder', 'paymentType', 'paymentMethod'])->latest('paid_on')->latest('id')->get();
        $years = range(now()->year - 2, now()->year + 1);
        $loans = $member->loans()->with('loanType')->get();
        $guarantees = $member->guarantees()->with('loan.member')->get();

        return view('members.show', compact('member', 'statement', 'year', 'years', 'payments', 'loans', 'guarantees'));
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
