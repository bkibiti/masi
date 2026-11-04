<?php

namespace App\Services;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic contribution ledger: rebuilds one member's dues, penalties and payment
 * allocations entirely from the contribution settings and their payments (ordered by
 * paid_on). Running it twice gives the same result (it deletes and recreates every time).
 */
class ContributionLedger
{
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    /**
     * One member's monthly statement for a given year, plus their all-time arrears and credit.
     * Used by the register, the member show page, and (later) the member portal.
     *
     * @return array<string, mixed>
     */
    public function statementForMember(Member $member, int $year): array
    {
        $settings = ContributionSetting::orderBy('start_date')->get();
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $currentPeriod = $today->startOfMonth()->toDateString();

        $duesByMonth = $member->dues()
            ->whereYear('period', $year)
            ->with('penalties')
            ->get()
            ->keyBy(fn (MemberDue $due) => (int) $due->period->format('n'));

        $months = [];
        $yearExpected = 0.0;
        $yearPaid = 0.0;
        $yearPenalties = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $period = CarbonImmutable::create($year, $m, 1);
            $due = $duesByMonth->get($m);

            if ($due) {
                $penaltyTotal = (float) $due->penalties->sum('amount');
                $yearExpected += (float) $due->amount;
                $yearPaid += (float) $due->amount_paid;
                $yearPenalties += $penaltyTotal;

                $months[] = [
                    'month' => $m,
                    'label' => $period->format('M'),
                    'status' => $due->status($today->toDateString(), $currentPeriod),
                    'expected' => (float) $due->amount,
                    'paid' => (float) $due->amount_paid,
                    'balance' => $due->balance(),
                    'penalty_total' => $penaltyTotal,
                    'penalty_count' => $due->penalties->count(),
                    'last_paid_on' => $this->lastPaidOn($due),
                ];

                continue;
            }

            $coveredBySetting = $this->settingCovering($settings, $period) !== null;
            $isFuture = $period->toDateString() > $currentPeriod;

            $months[] = [
                'month' => $m,
                'label' => $period->format('M'),
                'status' => ! $coveredBySetting ? 'No setting' : ($isFuture ? 'Not yet due' : 'No setting'),
                'expected' => 0.0,
                'paid' => 0.0,
                'balance' => 0.0,
                'penalty_total' => 0.0,
                'penalty_count' => 0,
                'last_paid_on' => null,
            ];
        }

        $allDues = $member->dues()->with('penalties')->get();
        // Arrears = only what is past due: a month whose due date hasn't passed yet (e.g. the
        // current month) is not in arrears.
        $dueBalance = (float) $allDues
            ->filter(fn (MemberDue $d) => $d->due_date->toDateString() < $today->toDateString())
            ->sum(fn (MemberDue $d) => $d->balance());
        $penaltyBalance = (float) $allDues->flatMap(fn (MemberDue $d) => $d->penalties)->sum(fn (MemberPenalty $p) => $p->balance());
        $totalPaid = (float) $member->payments()->ledgerRelevant()->sum('amount');
        $totalAllocated = (float) $allDues->sum('amount_paid') + (float) $allDues->flatMap(fn (MemberDue $d) => $d->penalties)->sum('amount_paid');

        return [
            'months' => $months,
            'year_totals' => [
                'expected' => $yearExpected,
                'paid' => $yearPaid,
                'penalties' => $yearPenalties,
                'balance' => max(0.0, $yearExpected - $yearPaid),
            ],
            'arrears' => $dueBalance + $penaltyBalance,
            'credit' => max(0.0, $totalPaid - $totalAllocated),
            'next_due' => $duesByMonth->get((int) $today->format('n')),
        ];
    }

    private function lastPaidOn(MemberDue $due): ?string
    {
        $latest = PaymentAllocation::query()
            ->where('allocatable_type', MemberDue::class)
            ->where('allocatable_id', $due->id)
            ->orWhere(function ($q) use ($due) {
                $q->where('allocatable_type', MemberPenalty::class)
                    ->whereIn('allocatable_id', $due->penalties->pluck('id'));
            })
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->max('payments.paid_on');

        return $latest;
    }

    /**
     * Arrears broken down by month, and how a given monthly-fee amount would be allocated
     * against them right now (oldest month first; monthly fees pay contributions only,
     * penalties are paid by Fines / Penalties payments). Read-only:
     * nothing is persisted. Used by the record-payment form's live preview.
     *
     * @return array<string, mixed>
     */
    public function previewAllocation(Member $member, float $amount): array
    {
        $dues = $member->dues()->with('penalties')->orderBy('period')->get();

        $remaining = $amount;
        $arrears = [];
        $allocation = [];

        foreach ($dues as $due) {
            $penaltyBalance = (float) $due->penalties->sum(fn (MemberPenalty $p) => $p->balance());
            $contributionBalance = $due->balance();

            if ($penaltyBalance <= 0 && $contributionBalance <= 0) {
                continue;
            }

            $arrears[] = [
                'period' => $due->period->toDateString(),
                'label' => $due->period->format('M Y'),
                'penalty_balance' => $penaltyBalance,
                'contribution_balance' => $contributionBalance,
                'total_balance' => $penaltyBalance + $contributionBalance,
            ];

            $penaltyPay = 0.0; // monthly fees never pay penalties
            $contributionPay = 0.0;

            if ($remaining > 0 && $contributionBalance > 0) {
                $contributionPay = min($remaining, $contributionBalance);
                $remaining -= $contributionPay;
            }

            if ($penaltyPay > 0 || $contributionPay > 0) {
                $allocation[] = [
                    'period' => $due->period->toDateString(),
                    'label' => $due->period->format('M Y'),
                    'penalty_pay' => $penaltyPay,
                    'contribution_pay' => $contributionPay,
                ];
            }
        }

        return [
            'arrears' => $arrears,
            'allocation' => $allocation,
            'credit_after' => max(0.0, $remaining),
        ];
    }

    /**
     * Same shape as previewAllocation(), but for a penalty-type payment: outstanding
     * penalties only, oldest due first, never touching a due's contribution balance.
     * Read-only: nothing is persisted. Used by the record-payment form's live preview.
     *
     * @return array<string, mixed>
     */
    public function previewPenaltyAllocation(Member $member, float $amount): array
    {
        $dues = $member->dues()->with('penalties')->orderBy('period')->get();

        $remaining = $amount;
        $arrears = [];
        $allocation = [];

        foreach ($dues as $due) {
            foreach ($due->penalties as $penalty) {
                $penaltyBalance = $penalty->balance();
                if ($penaltyBalance <= 0) {
                    continue;
                }

                $label = $due->period->format('M Y').' penalty';

                $arrears[] = [
                    'period' => $due->period->toDateString(),
                    'label' => $label,
                    'penalty_balance' => $penaltyBalance,
                    'contribution_balance' => 0.0,
                    'total_balance' => $penaltyBalance,
                ];

                $penaltyPay = 0.0;
                if ($remaining > 0) {
                    $penaltyPay = min($remaining, $penaltyBalance);
                    $remaining -= $penaltyPay;
                }

                if ($penaltyPay > 0) {
                    $allocation[] = [
                        'period' => $due->period->toDateString(),
                        'label' => $label,
                        'penalty_pay' => $penaltyPay,
                        'contribution_pay' => 0.0,
                    ];
                }
            }
        }

        return [
            'arrears' => $arrears,
            'allocation' => $allocation,
            'credit_after' => max(0.0, $remaining),
        ];
    }

    public function rebuildAll(): void
    {
        Member::query()->orderBy('id')->each(function (Member $member) {
            $this->rebuildForMember($member);
        });
    }

    public function rebuildForMember(Member $member): void
    {
        DB::transaction(function () use ($member) {
            $this->wipe($member);

            $settings = ContributionSetting::orderBy('start_date')->get();
            if ($settings->isEmpty()) {
                return;
            }

            $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
            $currentMonth = $today->startOfMonth();
            $lastCompletedMonthEnd = $currentMonth->subDay();

            $startMonth = CarbonImmutable::instance($member->created_at)->setTimezone(self::TIMEZONE)->startOfMonth();
            if ($startMonth->gt($currentMonth)) {
                return;
            }

            // 1. Create a due for every month from the member's start month through the
            // current month that falls inside a setting's period.
            $duesState = [];
            for ($month = $startMonth; $month->lte($currentMonth); $month = $month->addMonth()) {
                $setting = $this->settingCovering($settings, $month);
                if (! $setting) {
                    continue;
                }

                $monthEnd = $month->endOfMonth()->startOfDay();

                $due = MemberDue::create([
                    'member_id' => $member->id,
                    'contribution_setting_id' => $setting->id,
                    'period' => $month->toDateString(),
                    'due_date' => $monthEnd->toDateString(),
                    'amount' => $setting->monthly_amount,
                    'amount_paid' => 0,
                ]);

                $duesState[] = [
                    'due' => $due,
                    'amount' => (float) $setting->monthly_amount,
                    'amount_paid' => 0.0,
                    'penalty_percent' => (float) $setting->penalty_percent,
                    // No penalty is charged before this date (null = penalties apply from the start).
                    'penalties_start_on' => $setting->penalties_start_on?->toDateString(),
                    'due_date' => $monthEnd,
                    'penalties' => [], // each: ['model' => MemberPenalty, 'amount' => float, 'amount_paid' => float]
                ];
            }

            if (empty($duesState)) {
                return;
            }

            // 2. Build the chronological event timeline: one "checkpoint" per due per
            // month-end that has fully passed, plus one "payment" per payment.
            $events = [];

            foreach ($duesState as $index => $state) {
                $checkpoint = $state['due_date'];
                while ($checkpoint->lte($lastCompletedMonthEnd)) {
                    $events[] = ['order' => 1, 'date' => $checkpoint, 'due_index' => $index];
                    // addMonthNoOverflow() correctly lands in the next calendar month without
                    // skipping ahead, but if the current day doesn't exist in every month in
                    // between (e.g. starting from Jan 31 -> Feb 28), it stays clamped on that
                    // day number instead of re-expanding to the new month's own end (Mar 31).
                    // Re-snap to that month's actual end every time to avoid drifting.
                    $next = $checkpoint->addMonthNoOverflow();
                    $checkpoint = $next->startOfMonth()->endOfMonth()->startOfDay();
                }
            }

            // "none"-type payments (e.g. loan repayments) never touch the ledger.
            $payments = Payment::where('member_id', $member->id)
                ->ledgerRelevant()
                ->with('paymentType')
                ->orderBy('paid_on')
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $events[] = [
                    'order' => 0, // a payment on the same date as a checkpoint counts before that checkpoint fires
                    'date' => CarbonImmutable::parse($payment->paid_on->toDateString(), self::TIMEZONE),
                    'payment' => $payment,
                ];
            }

            usort($events, function ($a, $b) {
                if (! $a['date']->eq($b['date'])) {
                    return $a['date']->lt($b['date']) ? -1 : 1;
                }

                return $a['order'] <=> $b['order'];
            });

            // 3. Replay the timeline. A monthly-fee payment pays penalties-then-contribution,
            // oldest due first (unchanged). A penalty-type payment only ever pays outstanding
            // penalties, oldest first; any leftover queues up as "penalty credit" that gets
            // applied the moment a new penalty is charged (see chargePenaltyIfOutstanding()).
            $penaltyCreditQueue = [];

            foreach ($events as $event) {
                if (isset($event['payment'])) {
                    if ($event['payment']->paymentType->isPenalty()) {
                        $this->allocatePenaltyPayment($event['payment'], $duesState, $penaltyCreditQueue);
                    } else {
                        $this->allocatePayment($event['payment'], $duesState);
                    }
                } else {
                    $this->chargePenaltyIfOutstanding($member, $duesState[$event['due_index']], $event['date'], $penaltyCreditQueue);
                }
            }

            // 4. Persist final balances.
            foreach ($duesState as $state) {
                $state['due']->update(['amount_paid' => $state['amount_paid']]);
                foreach ($state['penalties'] as $penaltyState) {
                    $penaltyState['model']->update(['amount_paid' => $penaltyState['amount_paid']]);
                }
            }
        });
    }

    private function wipe(Member $member): void
    {
        $dueIds = MemberDue::where('member_id', $member->id)->pluck('id');
        $penaltyIds = MemberPenalty::whereIn('member_due_id', $dueIds)->pluck('id');

        PaymentAllocation::where('allocatable_type', MemberPenalty::class)->whereIn('allocatable_id', $penaltyIds)->delete();
        PaymentAllocation::where('allocatable_type', MemberDue::class)->whereIn('allocatable_id', $dueIds)->delete();
        MemberPenalty::whereIn('id', $penaltyIds)->delete();
        MemberDue::whereIn('id', $dueIds)->delete();
    }

    private function settingCovering(Collection $settings, CarbonImmutable $month): ?ContributionSetting
    {
        $monthEnd = $month->endOfMonth();

        return $settings->first(function (ContributionSetting $setting) use ($month, $monthEnd) {
            return $setting->start_date->toDateString() <= $month->toDateString()
                && $setting->end_date->toDateString() >= $monthEnd->toDateString();
        });
    }

    /**
     * Pay off a payment: oldest due first, its penalties before its own contribution,
     * then move to the next due, until the payment is exhausted. Anything left over
     * stays unallocated (credit), to be picked up by a future due on the next rebuild.
     *
     * @param  array<int, array>  $duesState
     */
    private function allocatePayment(Payment $payment, array &$duesState): void
    {
        $remaining = (float) $payment->amount;

        // Monthly-fee payments pay monthly contributions only, oldest month first. Penalties are
        // paid only by Fines / Penalties payments (allocatePenaltyPayment()), because MASI
        // records fines separately; letting fees clear penalties too would pay them twice.
        foreach ($duesState as &$state) {
            if ($remaining <= 0) {
                break;
            }

            $balance = $state['amount'] - $state['amount_paid'];
            if ($balance > 0) {
                $pay = min($remaining, $balance);
                $state['amount_paid'] += $pay;
                $remaining -= $pay;

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'allocatable_type' => MemberDue::class,
                    'allocatable_id' => $state['due']->id,
                    'amount' => $pay,
                ]);
            }
        }
        unset($state);
    }

    /**
     * Pay off a penalty-type payment: outstanding penalties only, oldest due first, never
     * touching a due's contribution. Anything left over queues up as penalty credit, to be
     * applied the moment a new penalty is charged (see chargePenaltyIfOutstanding()).
     *
     * @param  array<int, array>  $duesState
     * @param  array<int, array{payment_id: int, amount: float}>  $penaltyCreditQueue
     */
    private function allocatePenaltyPayment(Payment $payment, array &$duesState, array &$penaltyCreditQueue): void
    {
        $remaining = (float) $payment->amount;

        foreach ($duesState as &$state) {
            if ($remaining <= 0) {
                break;
            }

            foreach ($state['penalties'] as &$penaltyState) {
                if ($remaining <= 0) {
                    break;
                }

                $balance = $penaltyState['amount'] - $penaltyState['amount_paid'];
                if ($balance <= 0) {
                    continue;
                }

                $pay = min($remaining, $balance);
                $penaltyState['amount_paid'] += $pay;
                $remaining -= $pay;

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'allocatable_type' => MemberPenalty::class,
                    'allocatable_id' => $penaltyState['model']->id,
                    'amount' => $pay,
                ]);
            }
            unset($penaltyState);
        }
        unset($state);

        if ($remaining > 0) {
            $penaltyCreditQueue[] = ['payment_id' => $payment->id, 'amount' => $remaining];
        }
    }

    /**
     * @param  array<int, array{payment_id: int, amount: float}>  $penaltyCreditQueue
     */
    private function chargePenaltyIfOutstanding(Member $member, array &$state, CarbonImmutable $evaluationDate, array &$penaltyCreditQueue): void
    {
        if ($state['amount_paid'] >= $state['amount']) {
            return;
        }

        $outstanding = $state['amount'] - $state['amount_paid'];
        // Rounded to the nearest 100 TZS, like Excel's ROUND(x, -2), so fines are easy to pay.
        $penaltyAmount = (float) round($outstanding * $state['penalty_percent'] / 100, -2);
        $chargedOn = $evaluationDate->addDay();

        // Penalties only began on the setting's "penalties start on" date: month-ends before it
        // pass without a fine, and whatever is still unpaid gets its first penalty on/after it.
        if (! empty($state['penalties_start_on']) && $chargedOn->toDateString() < $state['penalties_start_on']) {
            return;
        }

        $penalty = MemberPenalty::create([
            'member_id' => $member->id,
            'member_due_id' => $state['due']->id,
            'charged_on' => $chargedOn->toDateString(),
            'amount' => $penaltyAmount,
            'amount_paid' => 0,
        ]);

        // Pre-pay this brand-new penalty from any leftover penalty-type overpayment, oldest first.
        $penaltyPaid = 0.0;
        while ($penaltyPaid < $penaltyAmount && ! empty($penaltyCreditQueue)) {
            $take = min($penaltyCreditQueue[0]['amount'], $penaltyAmount - $penaltyPaid);

            PaymentAllocation::create([
                'payment_id' => $penaltyCreditQueue[0]['payment_id'],
                'allocatable_type' => MemberPenalty::class,
                'allocatable_id' => $penalty->id,
                'amount' => $take,
            ]);

            $penaltyPaid += $take;
            $penaltyCreditQueue[0]['amount'] -= $take;

            if ($penaltyCreditQueue[0]['amount'] <= 0) {
                array_shift($penaltyCreditQueue);
            }
        }

        if ($penaltyPaid > 0) {
            $penalty->amount_paid = $penaltyPaid;
            $penalty->save();
        }

        $state['penalties'][] = [
            'model' => $penalty,
            'amount' => $penaltyAmount,
            'amount_paid' => $penaltyPaid,
        ];
    }
}
