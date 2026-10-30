<?php

namespace App\Services;

use App\Models\LoanType;
use Carbon\CarbonImmutable;

/**
 * Pure repayment-schedule math, no database. Used both for the on-screen preview (before a
 * loan is saved) and for the real schedule created at disbursement, so the preview always
 * matches what gets persisted.
 */
class LoanCalculator
{
    /**
     * @return array<int, array{number: int, due_date: CarbonImmutable, principal: float, interest: float, amount: float}>
     */
    public function installments(float $principal, float $ratePercent, int $months, string $method, CarbonImmutable $disbursedOn): array
    {
        $dueDates = $this->dueDates($disbursedOn, $months);

        return $method === LoanType::METHOD_FIXED
            ? $this->fixedInstallments($principal, $ratePercent, $months, $dueDates)
            : $this->reducingInstallments($principal, $ratePercent, $months, $dueDates);
    }

    public function totalInterest(float $principal, float $ratePercent, int $months, string $method): float
    {
        if ($method === LoanType::METHOD_FIXED) {
            return (float) round($principal * $ratePercent / 100);
        }

        $rows = $this->reducingInstallments($principal, $ratePercent, $months, $this->dueDates(CarbonImmutable::now(), $months));

        return array_sum(array_column($rows, 'interest'));
    }

    /**
     * Installment 1 is due on the last day of the month after the disbursement month, then
     * the last day of each following month.
     *
     * @return array<int, CarbonImmutable>
     */
    private function dueDates(CarbonImmutable $disbursedOn, int $months): array
    {
        $dates = [];
        $cursor = $disbursedOn;

        for ($i = 0; $i < $months; $i++) {
            // addMonthNoOverflow() avoids skipping ahead when the day-of-month doesn't exist in
            // the next month; re-snapping to that month's own end avoids it staying clamped on
            // the earlier day number for every month after that (e.g. Jan 31 -> Feb 28 -> Mar 28).
            $cursor = $cursor->addMonthNoOverflow()->startOfMonth()->endOfMonth();
            $dates[] = $cursor;
        }

        return $dates;
    }

    /**
     * @param  array<int, CarbonImmutable>  $dueDates
     * @return array<int, array{number: int, due_date: CarbonImmutable, principal: float, interest: float, amount: float}>
     */
    private function reducingInstallments(float $principal, float $ratePercent, int $months, array $dueDates): array
    {
        $rate = $ratePercent / 100;

        $installmentAmount = $rate > 0
            ? (float) round($principal * $rate / (1 - (1 + $rate) ** -$months))
            : (float) round($principal / $months);

        $outstanding = $principal;
        $rows = [];

        for ($number = 1; $number <= $months; $number++) {
            $interest = (float) round($outstanding * $rate);

            if ($number < $months) {
                $principalPart = $installmentAmount - $interest;
                $amount = $installmentAmount;
            } else {
                // Last installment absorbs any rounding: its principal is exactly whatever
                // balance remains, so total principal across the schedule equals $principal.
                $principalPart = $outstanding;
                $amount = $principalPart + $interest;
            }

            $outstanding -= $principalPart;

            $rows[] = [
                'number' => $number,
                'due_date' => $dueDates[$number - 1],
                'principal' => $principalPart,
                'interest' => $interest,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, CarbonImmutable>  $dueDates
     * @return array<int, array{number: int, due_date: CarbonImmutable, principal: float, interest: float, amount: float}>
     */
    private function fixedInstallments(float $principal, float $ratePercent, int $months, array $dueDates): array
    {
        $totalInterest = (float) round($principal * $ratePercent / 100);
        $totalPayable = $principal + $totalInterest;

        $baseAmount = (float) round($totalPayable / $months);
        $baseInterest = (float) round($totalInterest / $months);

        $amountRunning = 0.0;
        $interestRunning = 0.0;
        $rows = [];

        for ($number = 1; $number <= $months; $number++) {
            if ($number < $months) {
                $amount = $baseAmount;
                $interest = $baseInterest;
            } else {
                $amount = $totalPayable - $amountRunning;
                $interest = $totalInterest - $interestRunning;
            }

            $amountRunning += $amount;
            $interestRunning += $interest;

            $rows[] = [
                'number' => $number,
                'due_date' => $dueDates[$number - 1],
                'principal' => $amount - $interest,
                'interest' => $interest,
                'amount' => $amount,
            ];
        }

        return $rows;
    }
}
