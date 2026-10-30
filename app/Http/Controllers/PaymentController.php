<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Services\ContributionLedger;
use App\Services\LoanLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->query('from_date');
        $to = $request->query('to_date');
        $memberId = $request->query('member_id');
        $paymentMethodId = $request->query('payment_method_id');
        $paymentTypeId = $request->query('payment_type_id');

        $payments = Payment::query()
            ->with(['member', 'recorder', 'paymentMethod', 'paymentType'])
            ->when($from, fn ($q) => $q->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_on', '<=', $to))
            ->when($memberId, fn ($q) => $q->where('member_id', $memberId))
            ->when($paymentMethodId, fn ($q) => $q->where('payment_method_id', $paymentMethodId))
            ->when($paymentTypeId, fn ($q) => $q->where('payment_type_id', $paymentTypeId))
            ->latest('paid_on')
            ->latest('id')
            ->get();

        $members = Member::orderBy('name')->get();
        $paymentMethods = PaymentMethod::orderBy('name')->get();
        $paymentTypes = PaymentType::orderBy('name')->get();

        return view('contributions.payments.index', compact(
            'payments', 'members', 'paymentMethods', 'paymentTypes', 'from', 'to', 'memberId', 'paymentMethodId', 'paymentTypeId'
        ));
    }

    /**
     * Live preview: for a member, amount and payment type, how it would be allocated
     * against their current arrears. Used by the record-payment form before saving.
     * There is no ledger preview for a "none" (not ledger-relevant) payment type.
     */
    public function preview(Request $request, ContributionLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type_id' => 'required|exists:payment_types,id',
        ]);

        $member = Member::findOrFail($data['member_id']);
        $paymentType = PaymentType::findOrFail($data['payment_type_id']);

        if ($paymentType->isLoan()) {
            $loans = $member->loans()
                ->whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED])
                ->with('loanType')
                ->get()
                ->map(fn (Loan $loan) => [
                    'id' => $loan->id,
                    'label' => $loan->loanType->name.' — TZS '.number_format($loan->effectiveAmount()).' ('.($loan->status === Loan::STATUS_DISBURSED ? 'active' : 'completed').')',
                    'outstanding' => $loan->outstandingBalance(),
                    'active' => $loan->status === Loan::STATUS_DISBURSED,
                ])
                ->values();

            return response()->json(['ledger_role' => $paymentType->ledger_role, 'arrears' => [], 'allocation' => [], 'credit_after' => null, 'loans' => $loans]);
        }

        if (! $paymentType->isLedgerRelevant()) {
            return response()->json(['ledger_role' => $paymentType->ledger_role, 'arrears' => [], 'allocation' => [], 'credit_after' => null, 'loans' => []]);
        }

        $preview = $paymentType->isPenalty()
            ? $ledger->previewPenaltyAllocation($member, (float) $data['amount'])
            : $ledger->previewAllocation($member, (float) $data['amount']);

        return response()->json([...$preview, 'ledger_role' => $paymentType->ledger_role, 'loans' => []]);
    }

    public function store(Request $request, ContributionLedger $ledger, LoanLedger $loanLedger): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $ledger, $loanLedger) {
            $payment = Payment::create([
                ...$data,
                'recorded_by' => auth()->id(),
            ]);

            $ledger->rebuildForMember($payment->member);

            if ($payment->loan_id) {
                $loanLedger->rebuildForLoan($payment->loan);
            }
        });

        session()->flash('alert-success', 'Payment recorded.');

        return redirect()->route('contributions.payments.index');
    }

    public function update(Request $request, Payment $payment, ContributionLedger $ledger, LoanLedger $loanLedger): RedirectResponse
    {
        $data = $this->validated($request, $payment);
        $previousLoanId = $payment->loan_id;

        DB::transaction(function () use ($data, $payment, $ledger, $loanLedger, $previousLoanId) {
            $payment->update($data);
            $ledger->rebuildForMember($payment->member);

            foreach (array_unique(array_filter([$previousLoanId, $payment->loan_id])) as $loanId) {
                $loanLedger->rebuildForLoan(Loan::find($loanId));
            }
        });

        session()->flash('alert-success', 'Payment updated.');

        return back();
    }

    public function destroy(Payment $payment, ContributionLedger $ledger, LoanLedger $loanLedger): RedirectResponse
    {
        DB::transaction(function () use ($payment, $ledger, $loanLedger) {
            $member = $payment->member;
            $loanId = $payment->loan_id;
            $payment->delete();
            $ledger->rebuildForMember($member);

            if ($loanId) {
                $loanLedger->rebuildForLoan(Loan::find($loanId));
            }
        });

        session()->flash('alert-success', 'Payment deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Payment $payment = null): array
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_type_id' => 'required|exists:payment_types,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        $paymentType = PaymentType::findOrFail($data['payment_type_id']);
        $data['loan_id'] = null;

        if ($paymentType->isLoan()) {
            abort_if(! $request->user()->can('Record Loan Repayments'), 403);

            $loanData = $request->validate([
                'loan_id' => 'required|exists:loans,id',
            ]);

            $loan = Loan::findOrFail($loanData['loan_id']);

            if ((int) $loan->member_id !== (int) $data['member_id']) {
                throw ValidationException::withMessages(['loan_id' => 'This loan does not belong to the selected member.']);
            }

            if (! in_array($loan->status, [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages(['loan_id' => 'This loan has not been disbursed yet.']);
            }

            app(LoanLedger::class)->validateNewPaymentAmount($loan, (float) $data['amount'], $payment);

            $data['loan_id'] = $loan->id;
        }

        return $data;
    }
}
