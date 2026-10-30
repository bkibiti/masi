<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\Setting;

/**
 * Eligibility and guarantor-threshold rules (rule 2). Used by both the apply-form preview and
 * LoanService's enforcement, so the preview a staff member sees always matches what gets
 * validated on submit.
 */
class LoanEligibility
{
    public const SETTING_KEY = 'loan_guarantor_threshold_percent';

    public const DEFAULT_THRESHOLD_PERCENT = 80.0;

    public function contributionsTotal(Member $member): float
    {
        return $member->totalContributionsPaid();
    }

    public function guarantorThresholdPercent(): float
    {
        $value = Setting::where('name', self::SETTING_KEY)->value('value');

        return $value !== null ? (float) $value : self::DEFAULT_THRESHOLD_PERCENT;
    }

    /**
     * How much of the requested amount must be covered by accepted guarantees: the amount
     * above the member's threshold limit (threshold % of their total contributions).
     */
    public function requiredGuarantee(Member $member, float $amount): float
    {
        $limit = $this->contributionsTotal($member) * $this->guarantorThresholdPercent() / 100;

        return max(0.0, $amount - $limit);
    }

    public function hasActiveLoan(Member $member): bool
    {
        return $member->loans()->whereIn('status', Loan::ACTIVE_STATUSES)->exists();
    }

    public function withinLoanTypeLimits(LoanType $loanType, float $amount, int $months): bool
    {
        return $amount >= (float) $loanType->min_amount
            && $amount <= (float) $loanType->max_amount
            && $months >= $loanType->min_duration_months
            && $months <= $loanType->max_duration_months;
    }

    /**
     * Everything the apply screen shows before submitting: total contributions, the threshold
     * limit, whether guarantors are needed, and how much must be guaranteed.
     *
     * @return array{contributions_total: float, threshold_percent: float, threshold_amount: float, guarantors_required: bool, required_guarantee_amount: float}
     */
    public function summary(Member $member, float $amount): array
    {
        $contributions = $this->contributionsTotal($member);
        $percent = $this->guarantorThresholdPercent();
        $thresholdAmount = $contributions * $percent / 100;
        $required = max(0.0, $amount - $thresholdAmount);

        return [
            'contributions_total' => $contributions,
            'threshold_percent' => $percent,
            'threshold_amount' => $thresholdAmount,
            'guarantors_required' => $required > 0,
            'required_guarantee_amount' => $required,
        ];
    }
}
