<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Support\Facades\DB;

/**
 * MASI rule: when a member pays a monthly contribution and a fine from an earlier month is
 * still unpaid - whether an automatic 5% penalty or a manual fine - the fine is paid first and
 * only the rest counts as monthly fee.
 *
 * The ledger keeps fees and fines apart (monthly-fee payments pay fees, Fines / Penalties
 * payments pay fines), so this rule is applied by SPLITTING the payment record: the part that
 * settles the fine becomes a "Fines / Penalties" payment with the same date, method and
 * reference; the original keeps the remainder as monthly fee.
 */
class PenaltyPaymentSplitter
{
    public function __construct(private readonly ContributionLedger $ledger) {}

    /**
     * Split a newly recorded monthly-fee payment so it first clears fines unpaid on its date.
     * Call after the ledger has been rebuilt with the payment included.
     *
     * @return array<string, mixed>|null the split made, or null when none was needed
     */
    public function splitPayment(Payment $payment): ?array
    {
        $payment->loadMissing('paymentType', 'member');

        if (! $payment->paymentType?->isMonthlyFee() || (float) $payment->amount <= 0) {
            return null;
        }

        $owed = $this->unpaidFinesOn($payment);
        if ($owed <= 0) {
            return null;
        }

        $split = $this->split($payment, min($owed, (float) $payment->amount));
        $this->ledger->rebuildForMember($payment->member);

        return $split;
    }

    /**
     * Apply the rule to a member's whole history: repeatedly take the oldest fine that is still
     * unpaid and settle it from the first monthly-fee payment made on or after the day it was
     * charged, recalculating after every split (a split reduces fees paid, which can change
     * later fines). Fines already settled by a separate Fines / Penalties payment are left alone.
     *
     * @return array<int, array<string, mixed>>
     */
    public function splitForMember(Member $member): array
    {
        $splits = [];

        for ($i = 0; $i < 500; $i++) {
            $this->ledger->rebuildForMember($member);

            $oldestDate = $this->oldestUnpaidFineDate($member);

            if (! $oldestDate) {
                break;
            }

            $payment = Payment::where('member_id', $member->id)
                ->whereHas('paymentType', fn ($q) => $q->where('ledger_role', PaymentType::ROLE_MONTHLY_FEE))
                ->whereDate('paid_on', '>=', $oldestDate)
                ->where('amount', '>', 0)
                ->orderBy('paid_on')->orderBy('id')
                ->first();

            if (! $payment) {
                break; // no later monthly payment to take it from: the fine stays unpaid
            }

            $owed = $this->unpaidFinesOn($payment);
            if ($owed <= 0) {
                break;
            }

            $splits[] = $this->split($payment, min($owed, (float) $payment->amount));
        }

        $this->ledger->rebuildForMember($member);

        return $splits;
    }

    /**
     * Fines still unpaid (as currently allocated) that were charged on or before the payment's
     * date - automatic penalties (charged_on) and manual fines (fined_on) alike.
     */
    private function unpaidFinesOn(Payment $payment): float
    {
        $penalties = (float) MemberPenalty::where('member_id', $payment->member_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereDate('charged_on', '<=', $payment->paid_on->toDateString())
            ->get()
            ->sum(fn (MemberPenalty $p) => $p->balance());

        $fines = (float) MemberFine::where('member_id', $payment->member_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereDate('fined_on', '<=', $payment->paid_on->toDateString())
            ->get()
            ->sum(fn (MemberFine $f) => $f->balance());

        return $penalties + $fines;
    }

    /** The date of the oldest unpaid fine across automatic penalties and manual fines, or null if none. */
    private function oldestUnpaidFineDate(Member $member): ?string
    {
        $oldestPenalty = MemberPenalty::where('member_id', $member->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('charged_on')->orderBy('id')
            ->first();

        $oldestFine = MemberFine::where('member_id', $member->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('fined_on')->orderBy('id')
            ->first();

        $dates = array_filter([
            $oldestPenalty?->charged_on->toDateString(),
            $oldestFine?->fined_on->toDateString(),
        ]);

        return empty($dates) ? null : min($dates);
    }

    /** @return array<string, mixed> */
    private function split(Payment $payment, float $fineAmount): array
    {
        $fineType = PaymentType::where('ledger_role', PaymentType::ROLE_PENALTY)->where('is_active', true)->orderBy('id')->firstOrFail();
        $fineAmount = round($fineAmount, 2);
        $original = (float) $payment->amount;

        return DB::transaction(function () use ($payment, $fineAmount, $original, $fineType) {
            if ($fineAmount >= $original) {
                // The whole payment went to fines.
                $payment->update([
                    'payment_type_id' => $fineType->id,
                    'notes' => trim(($payment->notes ? $payment->notes.' · ' : '').'Whole payment applied to unpaid fines'),
                ]);

                return ['member' => $payment->member->name, 'paid_on' => $payment->paid_on->toDateString(), 'reference' => $payment->reference, 'original' => $original, 'fine' => $original, 'fee' => 0.0];
            }

            Payment::create([
                'member_id' => $payment->member_id,
                'payment_method_id' => $payment->payment_method_id,
                'payment_type_id' => $fineType->id,
                'amount' => $fineAmount,
                'paid_on' => $payment->paid_on->toDateString(),
                'reference' => $payment->reference,
                'notes' => 'Fine paid first from monthly payment of TZS '.number_format($original).' (#'.$payment->id.')',
                'recorded_by' => $payment->recorded_by,
            ]);

            $payment->update([
                'amount' => $original - $fineAmount,
                'notes' => trim(($payment->notes ? $payment->notes.' · ' : '').'TZS '.number_format($fineAmount).' of TZS '.number_format($original).' went to unpaid fines'),
            ]);

            return ['member' => $payment->member->name, 'paid_on' => $payment->paid_on->toDateString(), 'reference' => $payment->reference, 'original' => $original, 'fine' => $fineAmount, 'fee' => $original - $fineAmount];
        });
    }
}
