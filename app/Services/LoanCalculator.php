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
    /**
     * $installmentRounding: the installment is rounded UP to a multiple of this (1 = whole TZS,
     * 1000 = next 1,000, as in the MASI Excel sheet). $interestRounding: each month's interest is
     * rounded to the NEAREST multiple of this (1 = whole TZS, 100 = nearest 100). All installments
     * are equal, as in the MASI Excel sheet, so rounding can make total principal slightly exceed
     * the loan (e.g. 3,500,000 at 3% over 4 months: 4 x 942,000, principal 3,501,700).
     */
    public function installments(float $principal, float $ratePercent, int $months, string $method, CarbonImmutable $disbursedOn, int $installmentRounding = 1, int $interestRounding = 1): array
    {
        $dueDates = $this->dueDates($disbursedOn, $months);

        return $method === LoanType::METHOD_FIXED
            ? $this->fixedInstallments($principal, $ratePercent, $months, $dueDates, $installmentRounding, $interestRounding)
            : $this->reducingInstallments($principal, $ratePercent, $months, $dueDates, $installmentRounding, $interestRounding);
    }

    public function totalInterest(float $principal, float $ratePercent, int $months, string $method, int $installmentRounding = 1, int $interestRounding = 1): float
    {
        if ($method === LoanType::METHOD_FIXED) {
            return $this->roundTo($principal * $ratePercent / 100, $interestRounding);
        }

        $rows = $this->reducingInstallments($principal, $ratePercent, $months, $this->dueDates(CarbonImmutable::now(), $months), $installmentRounding, $interestRounding);

        return array_sum(array_column($rows, 'interest'));
    }

    /** Round to the nearest multiple of $step (whole TZS when $step <= 1). */
    private function roundTo(float $value, int $step): float
    {
        return $step <= 1 ? (float) round($value) : (float) (round($value / $step) * $step);
    }

    /** Round UP to a multiple of $step (plain whole-TZS rounding when $step <= 1). */
    private function roundUpTo(float $value, int $step): float
    {
        // round(…, 6) first so float noise like 942000.0000001 doesn't jump to the next step.
        return $step <= 1 ? (float) round($value) : (float) (ceil(round($value, 6) / $step) * $step);
    }

    /**
     * Due dates run monthly from the day the loan was released: released 10 Jan -> due 10 Feb,
     * 10 Mar, ... Each date is counted from the release date itself (not from the previous due
     * date), so a loan released on the 31st is due on the 28th/29th in February and back on the
     * 31st in March.
     *
     * @return array<int, CarbonImmutable>
     */
    private function dueDates(CarbonImmutable $disbursedOn, int $months): array
    {
        $dates = [];

        for ($i = 1; $i <= $months; $i++) {
            $dates[] = $disbursedOn->startOfDay()->addMonthsNoOverflow($i);
        }

        return $dates;
    }

    /**
     * @param  array<int, CarbonImmutable>  $dueDates
     * @return array<int, array{number: int, due_date: CarbonImmutable, principal: float, interest: float, amount: float}>
     */
    private function reducingInstallments(float $principal, float $ratePercent, int $months, array $dueDates, int $installmentRounding = 1, int $interestRounding = 1): array
    {
        $rate = $ratePercent / 100;

        $installmentAmount = $this->roundUpTo(
            $rate > 0 ? $principal * $rate / (1 - (1 + $rate) ** -$months) : $principal / $months,
            $installmentRounding,
        );

        $outstanding = $principal;
        $rows = [];

        for ($number = 1; $number <= $months; $number++) {
            $interest = $this->roundTo($outstanding * $rate, $interestRounding);

            if ($number < $months) {
                // Never take more principal than is left (only possible with coarse rounding on a
                // tiny loan).
                $principalPart = min($installmentAmount - $interest, $outstanding);
                $amount = $principalPart + $interest;
            } else {
                // Like the MASI Excel sheet, the last installment is the same as the others, so
                // all installments are equal; any rounding surplus counts as extra principal.
                // (If rounding ever left it short of the balance, it is raised to clear the loan.)
                // A tiny loan already cleared early by coarse rounding has nothing left to pay.
                $amount = $outstanding > 0 ? max($installmentAmount, $outstanding + $interest) : 0.0;
                $principalPart = $amount - $interest;
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
    private function fixedInstallments(float $principal, float $ratePercent, int $months, array $dueDates, int $installmentRounding = 1, int $interestRounding = 1): array
    {
        $totalInterest = $this->roundTo($principal * $ratePercent / 100, $interestRounding);
        $totalPayable = $principal + $totalInterest;

        $baseAmount = $this->roundUpTo($totalPayable / $months, $installmentRounding);
        $baseInterest = $this->roundTo($totalInterest / $months, $interestRounding);

        $amountRunning = 0.0;
        $interestRunning = 0.0;
        $rows = [];

        for ($number = 1; $number <= $months; $number++) {
            if ($number < $months) {
                $amount = min($baseAmount, $totalPayable - $amountRunning);
                $interest = min($baseInterest, $totalInterest - $interestRunning, $amount);
            } else {
                // Equal installments like the Excel sheet: the last is the same as the others
                // (raised only if needed to clear the loan); any surplus counts as principal.
                $amount = max($baseAmount, $totalPayable - $amountRunning);
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
