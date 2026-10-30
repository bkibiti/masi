<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Payment;
use App\Services\ContributionLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->query('from_date');
        $to = $request->query('to_date');
        $memberId = $request->query('member_id');

        $payments = Payment::query()
            ->with(['member', 'recorder'])
            ->when($from, fn ($q) => $q->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_on', '<=', $to))
            ->when($memberId, fn ($q) => $q->where('member_id', $memberId))
            ->latest('paid_on')
            ->latest('id')
            ->get();

        $members = Member::orderBy('name')->get();

        return view('contributions.payments.index', compact('payments', 'members', 'from', 'to', 'memberId'));
    }

    /**
     * Live preview: for a member and amount, how it would be allocated against their
     * current arrears. Used by the record-payment form before saving.
     */
    public function preview(Request $request, ContributionLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $member = Member::findOrFail($data['member_id']);

        return response()->json($ledger->previewAllocation($member, (float) $data['amount']));
    }

    public function store(Request $request, ContributionLedger $ledger): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $ledger) {
            $payment = Payment::create([
                ...$data,
                'recorded_by' => auth()->id(),
            ]);

            $ledger->rebuildForMember($payment->member);
        });

        session()->flash('alert-success', 'Payment recorded.');

        return redirect()->route('contributions.payments.index');
    }

    public function update(Request $request, Payment $payment, ContributionLedger $ledger): RedirectResponse
    {
        $data = $this->validated($request, $payment);

        DB::transaction(function () use ($data, $payment, $ledger) {
            $payment->update($data);
            $ledger->rebuildForMember($payment->member);
        });

        session()->flash('alert-success', 'Payment updated.');

        return back();
    }

    public function destroy(Payment $payment, ContributionLedger $ledger): RedirectResponse
    {
        DB::transaction(function () use ($payment, $ledger) {
            $member = $payment->member;
            $payment->delete();
            $ledger->rebuildForMember($member);
        });

        session()->flash('alert-success', 'Payment deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Payment $payment = null): array
    {
        return $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
