<?php

namespace App\Services;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic contribution ledger: rebuilds one member's dues, automatic penalties and
 * payment allocations entirely from the contribution settings and their payments (ordered by
 * paid_on). Running it twice gives the same result (it deletes and recreates every time).
 * Manual fines (MemberFine) are never deleted or recreated here - only their amount_paid and
 * payment allocations are recalculated.
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
        $fines = $member->fines()->with('fineType')->get();
        // Arrears = only what is past due: a month whose due date hasn't passed yet (e.g. the
        // current month) is not in arrears. Manual fines are always due immediately.
        $dueBalance = (float) $allDues
            ->filter(fn (MemberDue $d) => $d->due_date->toDateString() < $today->toDateString())
            ->sum(fn (MemberDue $d) => $d->balance());
        $penaltyBalance = (float) $allDues->flatMap(fn (MemberDue $d) => $d->penalties)->sum(fn (MemberPenalty $p) => $p->balance());
        $fineBalance = (float) $fines->sum(fn (MemberFine $f) => $f->balance());
        $totalPaid = (float) $member->payments()->ledgerRelevant()->sum('amount');
        $totalAllocated = (float) $allDues->sum('amount_paid')
            + (float) $allDues->flatMap(fn (MemberDue $d) => $d->penalties)->sum('amount_paid')
            + (float) $fines->sum('amount_paid');

        return [
            'months' => $months,
            'year_totals' => [
                'expected' => $yearExpected,
                'paid' => $yearPaid,
                'penalties' => $yearPenalties,
                'balance' => max(0.0, $yearExpected - $yearPaid),
            ],
            'arrears' => $dueBalance + $penaltyBalance + $fineBalance,
            'fines' => [
                'items' => $fines,
                'charged' => (float) $fines->sum('amount'),
                'paid' => (float) $fines->sum('amount_paid'),
                'balance' => $fineBalance,
            ],
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
        $fines = $member->fines()->with('fineType')->get();

        // One pool mixing automatic penalties and manual fines, oldest first by date then id
        // (same-day ties settle the automatic penalty before the manual fine).
        $items = [];

        foreach ($dues as $due) {
            foreach ($due->penalties as $penalty) {
                $balance = $penalty->balance();
                if ($balance <= 0) {
                    continue;
                }

                $items[] = [
                    'date' => $penalty->charged_on->toDateString(),
                    'kind' => 0,
                    'id' => $penalty->id,
                    'period' => $due->period->toDateString(),
                    'label' => $due->period->format('M Y').' penalty',
                    'balance' => $balance,
                ];
            }
        }

        foreach ($fines as $fine) {
            $balance = $fine->balance();
            if ($balance <= 0) {
                continue;
            }

            $items[] = [
                'date' => $fine->fined_on->toDateString(),
                'kind' => 1,
                'id' => $fine->id,
                'period' => $fine->fined_on->toDateString(),
                'label' => $fine->fineType->name.' ('.$fine->fined_on->format('j M Y').')',
                'balance' => $balance,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['date'] !== $b['date']) {
                return $a['date'] < $b['date'] ? -1 : 1;
            }
            if ($a['kind'] !== $b['kind']) {
                return $a['kind'] <=> $b['kind'];
            }

            return $a['id'] <=> $b['id'];
        });

        $remaining = $amount;
        $arrears = [];
        $allocation = [];

        foreach ($items as $item) {
            $arrears[] = [
                'period' => $item['period'],
                'label' => $item['label'],
                'penalty_balance' => $item['balance'],
                'contribution_balance' => 0.0,
                'total_balance' => $item['balance'],
            ];

            $penaltyPay = 0.0;
            if ($remaining > 0) {
                $penaltyPay = min($remaining, $item['balance']);
                $remaining -= $penaltyPay;
            }

            if ($penaltyPay > 0) {
                $allocation[] = [
                    'period' => $item['period'],
                    'label' => $item['label'],
                    'penalty_pay' => $penaltyPay,
                    'contribution_pay' => 0.0,
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
     * One combined, newest-first list mixing automatic penalties and manual fines for one
     * member: each entry a plain object with a shared shape, so callers don't need to duck-type
     * between MemberPenalty and MemberFine. Used by the member show page and My Account.
     *
     * @return array<int, object>
     */
    public function feeCharges(Member $member): array
    {
        $penalties = $member->penalties()->with('due.setting')->get();
        $fines = $member->fines()->with('fineType')->get();

        // Eloquent Collection::merge() expects Model items (it keys by getKey()), so the two
        // lists of plain stdClass records are combined as arrays instead.
        $automatic = $penalties->map(function (MemberPenalty $p) {
            $rate = (float) ($p->due?->setting?->penalty_percent ?? 0);

            return (object) [
                'kind' => 'automatic',
                'date' => $p->charged_on,
                'type_label' => 'Automatic 5%',
                'for_month' => $p->due?->period,
                'base' => $rate > 0 ? (float) $p->amount * 100 / $rate : null,
                'rate' => $rate,
                'amount' => (float) $p->amount,
                'amount_paid' => (float) $p->amount_paid,
                'balance' => $p->balance(),
                'is_fully_paid' => $p->isFullyPaid(),
                'reason' => null,
                'model' => $p,
            ];
        })->all();

        $manual = $fines->map(function (MemberFine $f) {
            return (object) [
                'kind' => 'manual',
                'date' => $f->fined_on,
                'type_label' => $f->fineType->name,
                'for_month' => null,
                'base' => null,
                'rate' => null,
                'amount' => (float) $f->amount,
                'amount_paid' => (float) $f->amount_paid,
                'balance' => $f->balance(),
                'is_fully_paid' => $f->isFullyPaid(),
                'reason' => $f->reason,
                'model' => $f,
            ];
        })->all();

        $records = [...$automatic, ...$manual];

        usort($records, function ($a, $b) {
            $dateA = $a->date->toDateString();
            $dateB = $b->date->toDateString();
            if ($dateA !== $dateB) {
                return $dateA > $dateB ? -1 : 1;
            }

            return $b->model->id <=> $a->model->id;
        });

        return $records;
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
            $finesState = $this->resetFines($member);

            $settings = ContributionSetting::orderBy('start_date')->get();
            $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
            $currentMonth = $today->startOfMonth();
            $lastCompletedMonthEnd = $currentMonth->subDay();

            $startMonth = CarbonImmutable::instance($member->created_at)->setTimezone(self::TIMEZONE)->startOfMonth();

            // 1. Create a due for every month from the member's start month through the
            // current month that falls inside a setting's period. Manual fines don't depend on
            // any of this - they exist independently of contribution settings and dues.
            $duesState = [];
            if ($settings->isNotEmpty() && ! $startMonth->gt($currentMonth)) {
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
            }

            // 2. Build the chronological event timeline: one "checkpoint" per due per
            // month-end that has fully passed, one "fine available" per manual fine (so a
            // fine charged after a penalty-payment overpayment can still absorb that credit),
            // plus one "payment" per payment.
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

            foreach ($finesState as $index => $state) {
                $events[] = ['order' => 1, 'date' => CarbonImmutable::parse($state['date'], self::TIMEZONE), 'fine_index' => $index];
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
            // oldest due first (unchanged). A penalty-type payment pays outstanding automatic
            // penalties and unpaid manual fines together, oldest first by date then (penalty
            // before fine, then id); any leftover queues up as "penalty credit" that gets
            // applied the moment a new penalty is charged or a manual fine's date arrives.
            $penaltyCreditQueue = [];

            foreach ($events as $event) {
                if (isset($event['payment'])) {
                    if ($event['payment']->paymentType->isPenalty()) {
                        $this->allocatePenaltyPayment($event['payment'], $duesState, $finesState, $penaltyCreditQueue);
                    } else {
                        $this->allocatePayment($event['payment'], $duesState);
                    }
                } elseif (isset($event['fine_index'])) {
                    $this->drainCreditInto($finesState[$event['fine_index']], $penaltyCreditQueue);
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

            $this->persistFines($finesState);
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

    /**
     * Manual fines are never deleted or recreated here - only their payment allocations and
     * amount_paid are recalculated. Returns the in-memory state the replay allocates against;
     * persistFines() writes it back at the end.
     *
     * @return array<int, array{model: MemberFine, amount: float, amount_paid: float, date: string}>
     */
    private function resetFines(Member $member): array
    {
        $fineIds = MemberFine::withTrashed()->where('member_id', $member->id)->pluck('id');
        PaymentAllocation::where('allocatable_type', MemberFine::class)->whereIn('allocatable_id', $fineIds)->delete();

        return $member->fines()->orderBy('fined_on')->orderBy('id')->get()
            ->map(fn (MemberFine $fine) => [
                'model' => $fine,
                'amount' => (float) $fine->amount,
                'amount_paid' => 0.0,
                'date' => $fine->fined_on->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{model: MemberFine, amount: float, amount_paid: float, date: string}>  $finesState
     */
    private function persistFines(array $finesState): void
    {
        foreach ($finesState as $state) {
            $state['model']->update(['amount_paid' => $state['amount_paid']]);
        }
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
     * Pay off a penalty-type payment: outstanding automatic penalties and unpaid manual fines
     * together, oldest first by date (ties: automatic penalty before manual fine, then id).
     * Never touches a due's contribution. Anything left over queues up as penalty credit, to be
     * applied the moment a new penalty is charged or a manual fine's date arrives (see
     * chargePenaltyIfOutstanding() and drainCreditInto()).
     *
     * @param  array<int, array>  $duesState
     * @param  array<int, array{model: MemberFine, amount: float, amount_paid: float, date: string}>  $finesState
     * @param  array<int, array{payment_id: int, amount: float}>  $penaltyCreditQueue
     */
    private function allocatePenaltyPayment(Payment $payment, array &$duesState, array &$finesState, array &$penaltyCreditQueue): void
    {
        $remaining = (float) $payment->amount;
        $paymentDate = $payment->paid_on->toDateString();

        $pool = [];

        foreach ($duesState as $dueIndex => $state) {
            foreach ($state['penalties'] as $penIndex => $penaltyState) {
                $balance = $penaltyState['amount'] - $penaltyState['amount_paid'];
                if ($balance > 0) {
                    $pool[] = [
                        'date' => $penaltyState['model']->charged_on->toDateString(),
                        'kind' => 0,
                        'id' => $penaltyState['model']->id,
                        'due_index' => $dueIndex,
                        'pen_index' => $penIndex,
                        'balance' => $balance,
                    ];
                }
            }
        }

        foreach ($finesState as $fineIndex => $state) {
            // A fine can only be paid by a payment made on or after it was charged.
            if ($state['date'] > $paymentDate) {
                continue;
            }

            $balance = $state['amount'] - $state['amount_paid'];
            if ($balance > 0) {
                $pool[] = [
                    'date' => $state['date'],
                    'kind' => 1,
                    'id' => $state['model']->id,
                    'fine_index' => $fineIndex,
                    'balance' => $balance,
                ];
            }
        }

        usort($pool, function ($a, $b) {
            if ($a['date'] !== $b['date']) {
                return $a['date'] < $b['date'] ? -1 : 1;
            }
            if ($a['kind'] !== $b['kind']) {
                return $a['kind'] <=> $b['kind'];
            }

            return $a['id'] <=> $b['id'];
        });

        foreach ($pool as $item) {
            if ($remaining <= 0) {
                break;
            }

            $pay = min($remaining, $item['balance']);
            $remaining -= $pay;

            if ($item['kind'] === 0) {
                $duesState[$item['due_index']]['penalties'][$item['pen_index']]['amount_paid'] += $pay;
                $allocatableType = MemberPenalty::class;
                $allocatableId = $item['id'];
            } else {
                $finesState[$item['fine_index']]['amount_paid'] += $pay;
                $allocatableType = MemberFine::class;
                $allocatableId = $item['id'];
            }

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'allocatable_type' => $allocatableType,
                'allocatable_id' => $allocatableId,
                'amount' => $pay,
            ]);
        }

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

        $penaltyState = ['model' => $penalty, 'amount' => $penaltyAmount, 'amount_paid' => 0.0];
        // Pre-pay this brand-new penalty from any leftover penalty-type overpayment, oldest first.
        $this->drainCreditInto($penaltyState, $penaltyCreditQueue);

        $state['penalties'][] = $penaltyState;
    }

    /**
     * Drain queued penalty-payment overpayment into a newly-available charge (a fresh automatic
     * penalty, or a manual fine whose date has just arrived in the timeline), oldest credit first.
     *
     * @param  array{model: MemberPenalty|MemberFine, amount: float, amount_paid: float}  $itemState
     * @param  array<int, array{payment_id: int, amount: float}>  $penaltyCreditQueue
     */
    private function drainCreditInto(array &$itemState, array &$penaltyCreditQueue): void
    {
        while ($itemState['amount_paid'] < $itemState['amount'] && ! empty($penaltyCreditQueue)) {
            $take = min($penaltyCreditQueue[0]['amount'], $itemState['amount'] - $itemState['amount_paid']);

            PaymentAllocation::create([
                'payment_id' => $penaltyCreditQueue[0]['payment_id'],
                'allocatable_type' => get_class($itemState['model']),
                'allocatable_id' => $itemState['model']->id,
                'amount' => $take,
            ]);

            $itemState['amount_paid'] += $take;
            $penaltyCreditQueue[0]['amount'] -= $take;

            if ($penaltyCreditQueue[0]['amount'] <= 0) {
                array_shift($penaltyCreditQueue);
            }
        }
    }
}
