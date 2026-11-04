<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPenalty;
use App\Models\LoanStatusHistory;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic loan ledger: rebuilds one loan's installments-paid, penalties and status
 * entirely from its schedule and its payments (ordered by paid_on). Running it twice gives
 * the same result (it deletes and recreates every penalty every time). Mirrors
 * ContributionLedger, but never touches member_dues/contributions (rule 6/k).
 */
class LoanLedger
{
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    public function rebuildAll(): void
    {
        Loan::whereIn('status', [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED])
            ->orderBy('id')
            ->each(fn (Loan $loan) => $this->rebuildForLoan($loan));
    }

    public function rebuildForLoan(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            $loan = $loan->fresh();

            if (! in_array($loan->status, [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED], true)) {
                return;
            }

            $this->wipe($loan);

            $installments = $loan->installments()->orderBy('number')->get();
            if ($installments->isEmpty()) {
                return;
            }

            $installmentsState = $installments->map(fn ($i) => [
                'model' => $i,
                'amount' => (float) $i->amount,
                'amount_paid' => 0.0,
            ])->all();

            $penaltiesState = [];

            $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
            $periodEnd = CarbonImmutable::parse($installments->last()->due_date->toDateString(), self::TIMEZONE);

            $events = [];

            // Penalties are charged monthly once the loan's period has fully ended, while a balance
            // remains (rule 5) -- never during the period itself. They follow the loan's own
            // monthly cycle: the day after the last due date, then the same day each month after
            // (last due 10 Mar -> penalties on 11 Mar, 11 Apr, 11 May, ...).
            for ($k = 0; ; $k++) {
                $checkpoint = $periodEnd->addMonthsNoOverflow($k)->addDay();
                if ($checkpoint->toDateString() > $today->toDateString()) {
                    break;
                }
                $events[] = ['order' => 1, 'date' => $checkpoint];
            }

            $payments = $loan->payments()->orderBy('paid_on')->orderBy('id')->get();
            foreach ($payments as $payment) {
                $events[] = [
                    'order' => 0, // a payment on the same date as a checkpoint counts before it fires
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

            foreach ($events as $event) {
                if (isset($event['payment'])) {
                    $this->allocatePayment($event['payment'], $penaltiesState, $installmentsState);
                } else {
                    $this->chargeMonthlyPenaltyIfOutstanding($loan, $installmentsState, $penaltiesState, $event['date']);
                }
            }

            foreach ($installmentsState as $state) {
                $state['model']->update(['amount_paid' => $state['amount_paid']]);
            }
            foreach ($penaltiesState as $state) {
                $state['model']->update(['amount_paid' => $state['amount_paid']]);
            }

            $this->syncCompletionStatus($loan, $this->outstandingFromState($installmentsState, $penaltiesState));
        });
    }

    /**
     * Outstanding balance as currently persisted (used to validate a new/edited payment
     * before it is saved -- overpayment beyond the loan's balance is not allowed, rule 6).
     */
    public function remainingBalance(Loan $loan): float
    {
        return $loan->fresh(['installments', 'penalties'])->outstandingBalance();
    }

    public function validateNewPaymentAmount(Loan $loan, float $amount, ?Payment $excludingPayment = null): void
    {
        $available = $this->remainingBalance($loan);

        if ($excludingPayment && (int) $excludingPayment->loan_id === $loan->id) {
            $available += (float) $excludingPayment->amount;
        }

        if ($amount > $available + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'This payment of TZS '.number_format($amount).' would exceed the loan\'s outstanding balance of TZS '.number_format($available).'.',
            ]);
        }
    }

    private function wipe(Loan $loan): void
    {
        LoanPenalty::where('loan_id', $loan->id)->delete();
    }

    /**
     * Pay off a payment: unpaid penalties oldest-first, then installments oldest-first.
     * ("Interest before principal" within an installment has no separate effect here, since
     * each installment tracks a single amount_paid rather than split interest/principal-paid
     * columns -- the schedule's own interest/principal split is what the UI shows.)
     *
     * @param  array<int, array>  $penaltiesState
     * @param  array<int, array>  $installmentsState
     */
    private function allocatePayment(Payment $payment, array &$penaltiesState, array &$installmentsState): void
    {
        $remaining = (float) $payment->amount;

        foreach ($penaltiesState as &$state) {
            if ($remaining <= 0) {
                break;
            }

            $balance = $state['amount'] - $state['amount_paid'];
            if ($balance <= 0) {
                continue;
            }

            $pay = min($remaining, $balance);
            $state['amount_paid'] += $pay;
            $remaining -= $pay;
        }
        unset($state);

        foreach ($installmentsState as &$state) {
            if ($remaining <= 0) {
                break;
            }

            $balance = $state['amount'] - $state['amount_paid'];
            if ($balance <= 0) {
                continue;
            }

            $pay = min($remaining, $balance);
            $state['amount_paid'] += $pay;
            $remaining -= $pay;
        }
        unset($state);

        // Any leftover (should not happen -- validated before save) stays unapplied credit.
    }

    /**
     * The penalty base is the unpaid principal + interest only -- unpaid penalties are
     * excluded (rule 5), so penalties never compound on top of each other.
     *
     * @param  array<int, array>  $installmentsState
     * @param  array<int, array>  $penaltiesState
     */
    private function chargeMonthlyPenaltyIfOutstanding(Loan $loan, array &$installmentsState, array &$penaltiesState, CarbonImmutable $chargeDate): void
    {
        $outstanding = $this->installmentBalance($installmentsState);

        if ($outstanding <= 0) {
            return;
        }

        $penaltyAmount = (float) round($outstanding * (float) $loan->penalty_percent / 100);

        if ($penaltyAmount <= 0) {
            return;
        }

        $penalty = LoanPenalty::create([
            'loan_id' => $loan->id,
            'charged_on' => $chargeDate->toDateString(),
            'amount' => $penaltyAmount,
            'amount_paid' => 0,
        ]);

        $penaltiesState[] = ['model' => $penalty, 'amount' => $penaltyAmount, 'amount_paid' => 0.0];
    }

    /**
     * @param  array<int, array>  $installmentsState
     * @param  array<int, array>  $penaltiesState
     */
    private function outstandingFromState(array $installmentsState, array $penaltiesState): float
    {
        return $this->installmentBalance($installmentsState) + $this->penaltyBalance($penaltiesState);
    }

    /**
     * @param  array<int, array>  $installmentsState
     */
    private function installmentBalance(array $installmentsState): float
    {
        return array_sum(array_map(fn ($s) => max(0.0, $s['amount'] - $s['amount_paid']), $installmentsState));
    }

    /**
     * @param  array<int, array>  $penaltiesState
     */
    private function penaltyBalance(array $penaltiesState): float
    {
        return array_sum(array_map(fn ($s) => max(0.0, $s['amount'] - $s['amount_paid']), $penaltiesState));
    }

    private function syncCompletionStatus(Loan $loan, float $outstanding): void
    {
        $fullyPaid = $outstanding <= 0.0;

        if ($fullyPaid && $loan->status === Loan::STATUS_DISBURSED) {
            $loan->update(['status' => Loan::STATUS_COMPLETED, 'completed_at' => now()]);
            LoanStatusHistory::create([
                'loan_id' => $loan->id,
                'from_status' => Loan::STATUS_DISBURSED,
                'to_status' => Loan::STATUS_COMPLETED,
                'changed_by' => null,
                'note' => 'Fully paid.',
            ]);
        } elseif (! $fullyPaid && $loan->status === Loan::STATUS_COMPLETED) {
            $loan->update(['status' => Loan::STATUS_DISBURSED, 'completed_at' => null]);
            LoanStatusHistory::create([
                'loan_id' => $loan->id,
                'from_status' => Loan::STATUS_COMPLETED,
                'to_status' => Loan::STATUS_DISBURSED,
                'changed_by' => null,
                'note' => 'A payment was edited or removed; the loan has a balance again.',
            ]);
        }
    }
}
