<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanStatusHistory;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the loan workflow (rule 3). All calculation is delegated to LoanCalculator
 * (schedule) and LoanEligibility (guarantor threshold); this class only validates state
 * transitions, persists them, and records the audit trail.
 */
class LoanService
{
    public function __construct(
        private readonly LoanEligibility $eligibility,
        private readonly LoanCalculator $calculator,
    ) {}

    /**
     * @param  array<int, array{guarantor_member_id: int, guaranteed_amount: float}>  $guarantors
     */
    public function apply(Member $member, LoanType $loanType, float $amount, int $months, ?string $purpose, array $guarantors, User $actingUser, ?string $appliedOn = null): Loan
    {
        // Staff may back-date an application to record a loan that was already taken.
        $appliedAt = $this->workflowDate($appliedOn);

        if ($this->eligibility->hasActiveLoan($member)) {
            throw ValidationException::withMessages(['member_id' => 'This member already has an active loan.']);
        }

        if (! $loanType->is_active) {
            throw ValidationException::withMessages(['loan_type_id' => 'This loan type is not active.']);
        }

        if (! $this->eligibility->withinLoanTypeLimits($loanType, $amount, $months)) {
            throw ValidationException::withMessages([
                'requested_amount' => "Amount must be between TZS {$loanType->min_amount} and TZS {$loanType->max_amount}, and duration between {$loanType->min_duration_months} and {$loanType->max_duration_months} months.",
            ]);
        }

        $summary = $this->eligibility->summary($member, $amount);

        return DB::transaction(function () use ($member, $loanType, $amount, $months, $purpose, $guarantors, $actingUser, $summary, $appliedAt) {
            $status = $summary['guarantors_required'] ? Loan::STATUS_PENDING_GUARANTORS : Loan::STATUS_PENDING_APPROVAL;

            $loan = Loan::create([
                'member_id' => $member->id,
                'loan_type_id' => $loanType->id,
                'requested_amount' => $amount,
                'requested_duration_months' => $months,
                'purpose' => $purpose,
                'interest_method' => $loanType->interest_method,
                'interest_rate' => $loanType->interest_rate,
                'penalty_percent' => $loanType->penalty_percent,
                'installment_rounding' => $loanType->installment_rounding ?? 1,
                'interest_rounding' => $loanType->interest_rounding ?? 1,
                'status' => $status,
                'applicant_contributions_total' => $summary['contributions_total'],
                'required_guarantee_amount' => $summary['required_guarantee_amount'],
                'applied_by' => $actingUser->id,
                'applied_at' => $appliedAt,
            ]);

            if ($summary['guarantors_required']) {
                foreach ($guarantors as $guarantor) {
                    $this->addGuarantor($loan, (int) $guarantor['guarantor_member_id'], (float) $guarantor['guaranteed_amount']);
                }
            }

            $this->recordHistory($loan, null, $status, $actingUser, null, $appliedAt);

            return $loan;
        });
    }

    public function addGuarantor(Loan $loan, int $guarantorMemberId, float $amount, ?string $notes = null): LoanGuarantor
    {
        if ($loan->status !== Loan::STATUS_PENDING_GUARANTORS) {
            throw ValidationException::withMessages(['guarantor' => 'Guarantors can only be added while this loan is waiting for guarantors.']);
        }

        if ($guarantorMemberId === $loan->member_id) {
            throw ValidationException::withMessages(['guarantor' => 'A member cannot guarantee their own loan.']);
        }

        if ($loan->guarantors()->where('guarantor_member_id', $guarantorMemberId)->exists()) {
            throw ValidationException::withMessages(['guarantor' => 'This member is already a guarantor on this loan.']);
        }

        return LoanGuarantor::create([
            'loan_id' => $loan->id,
            'guarantor_member_id' => $guarantorMemberId,
            'guaranteed_amount' => $amount,
            'status' => LoanGuarantor::STATUS_PENDING,
            'notes' => $notes,
        ]);
    }

    public function removeGuarantor(Loan $loan, LoanGuarantor $guarantor): void
    {
        if ($loan->status !== Loan::STATUS_PENDING_GUARANTORS) {
            throw ValidationException::withMessages(['guarantor' => 'Guarantors can only be changed while this loan is waiting for guarantors.']);
        }

        $guarantor->delete();
    }

    /**
     * Accept or decline a guarantee — called from the guarantor's own My Account screen, or
     * by staff on their behalf — and re-evaluate whether the loan can move to/from the Loan
     * Officer.
     */
    public function recordGuarantorResponse(LoanGuarantor $guarantor, string $status, ?User $recordedBy, ?string $notes = null): LoanGuarantor
    {
        if (! in_array($status, [LoanGuarantor::STATUS_ACCEPTED, LoanGuarantor::STATUS_DECLINED], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid guarantor response.']);
        }

        $guarantor->update([
            'status' => $status,
            'responded_by' => $recordedBy?->id,
            'responded_at' => now(),
            'notes' => $notes,
        ]);

        $loan = $guarantor->loan;

        if ($loan->status === Loan::STATUS_PENDING_GUARANTORS && $loan->guaranteesCoverRequirement()) {
            $this->transition($loan, Loan::STATUS_PENDING_APPROVAL, $recordedBy, 'Guarantees now cover the required amount.');
        } elseif ($loan->status === Loan::STATUS_PENDING_APPROVAL && ! $loan->guaranteesCoverRequirement()) {
            $this->transition($loan, Loan::STATUS_PENDING_GUARANTORS, $recordedBy, 'A guarantor declined; guarantees no longer cover the required amount.');
        }

        return $guarantor->refresh();
    }

    /**
     * The Loan Officer may approve a lower amount or shorter duration (within the loan type's
     * current limits); the guarantor requirement is recalculated against the final amount.
     */
    public function approve(Loan $loan, User $officer, ?float $approvedAmount = null, ?int $approvedMonths = null, ?string $approvedOn = null): Loan
    {
        if ($loan->status !== Loan::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only a loan pending approval can be approved.']);
        }

        $approvedAt = $this->workflowDate($approvedOn);

        // Backlog entry: a loan approved before the date its application was typed in. The
        // application can't come after its approval, so move the application date back to the
        // approval date (and its history entry with it).
        $this->moveEarlierStepsBack($loan, $approvedAt);

        $this->guardAgainstSelfDealing($loan, $officer, 'approve');

        $finalAmount = $approvedAmount ?? (float) $loan->requested_amount;
        $finalMonths = $approvedMonths ?? $loan->requested_duration_months;

        if ($finalAmount > (float) $loan->requested_amount || $finalMonths > $loan->requested_duration_months) {
            throw ValidationException::withMessages(['approved_amount' => 'The approved amount/duration cannot exceed what was requested.']);
        }

        $loanType = $loan->loanType;
        if (! $this->eligibility->withinLoanTypeLimits($loanType, $finalAmount, $finalMonths)) {
            throw ValidationException::withMessages(['approved_amount' => "Amount must be between TZS {$loanType->min_amount} and TZS {$loanType->max_amount}, and duration between {$loanType->min_duration_months} and {$loanType->max_duration_months} months."]);
        }

        $requiredGuarantee = $this->eligibility->requiredGuarantee($loan->member, $finalAmount);

        return DB::transaction(function () use ($loan, $finalAmount, $finalMonths, $requiredGuarantee, $officer, $approvedAt) {
            $loan->update(['required_guarantee_amount' => $requiredGuarantee]);

            if ($requiredGuarantee > 0 && ! $loan->guaranteesCoverRequirement()) {
                throw ValidationException::withMessages(['approved_amount' => 'Accepted guarantees no longer cover the required amount for this approval.']);
            }

            $loan->update([
                'approved_amount' => $finalAmount,
                'approved_duration_months' => $finalMonths,
                'status' => Loan::STATUS_APPROVED,
                'approved_by' => $officer->id,
                'approved_at' => $approvedAt,
            ]);

            $this->recordHistory($loan, Loan::STATUS_PENDING_APPROVAL, Loan::STATUS_APPROVED, $officer, null, $approvedAt);

            return $loan;
        });
    }

    public function reject(Loan $loan, User $officer, string $reason): Loan
    {
        if ($loan->status !== Loan::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only a loan pending approval can be rejected.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A comment is required to reject a loan.']);
        }

        $fromStatus = $loan->status;

        $loan->update([
            'status' => Loan::STATUS_REJECTED,
            'rejected_by' => $officer->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->recordHistory($loan, $fromStatus, Loan::STATUS_REJECTED, $officer, $reason);

        return $loan;
    }

    public function cancel(Loan $loan, ?User $actingUser, ?string $reason = null): Loan
    {
        if (! in_array($loan->status, [Loan::STATUS_PENDING_GUARANTORS, Loan::STATUS_PENDING_APPROVAL], true)) {
            throw ValidationException::withMessages(['status' => 'A loan can only be cancelled before it is approved.']);
        }

        $fromStatus = $loan->status;

        $loan->update([
            'status' => Loan::STATUS_CANCELLED,
            'cancelled_by' => $actingUser?->id,
            'cancelled_at' => now(),
        ]);

        $this->recordHistory($loan, $fromStatus, Loan::STATUS_CANCELLED, $actingUser, $reason);

        return $loan;
    }

    /**
     * Releases the funds: records how/when, and creates the repayment schedule. The schedule
     * generation and the loan's status flip happen in one transaction (instruction: use DB
     * transactions for disbursement).
     */
    public function disburse(Loan $loan, User $treasurer, string $disbursedOn, int $paymentMethodId, ?string $reference, ?string $notes): Loan
    {
        if ($loan->status !== Loan::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only an approved loan can be disbursed.']);
        }

        $this->guardAgainstSelfDealing($loan, $treasurer, 'disburse');

        return DB::transaction(function () use ($loan, $treasurer, $disbursedOn, $paymentMethodId, $reference, $notes) {
            $disbursedOnDate = CarbonImmutable::parse($disbursedOn);

            // Backlog entry: released before the approval/application dates that were typed in.
            $this->moveEarlierStepsBack($loan, $disbursedOnDate->setTime(12, 0));

            $rows = $this->calculator->installments(
                $loan->effectiveAmount(),
                (float) $loan->interest_rate,
                $loan->effectiveDurationMonths(),
                $loan->interest_method,
                $disbursedOnDate,
                (int) ($loan->installment_rounding ?? 1),
                (int) ($loan->interest_rounding ?? 1),
            );

            foreach ($rows as $row) {
                $loan->installments()->create([
                    'number' => $row['number'],
                    'due_date' => $row['due_date']->toDateString(),
                    'principal' => $row['principal'],
                    'interest' => $row['interest'],
                    'amount' => $row['amount'],
                    'amount_paid' => 0,
                ]);
            }

            $loan->update([
                'status' => Loan::STATUS_DISBURSED,
                'disbursed_by' => $treasurer->id,
                'disbursed_at' => now(),
                'disbursed_on' => $disbursedOnDate->toDateString(),
                'disbursement_payment_method_id' => $paymentMethodId,
                'disbursement_reference' => $reference,
                'disbursement_notes' => $notes,
            ]);

            $this->recordHistory($loan, Loan::STATUS_APPROVED, Loan::STATUS_DISBURSED, $treasurer, null, $disbursedOnDate->setTime(12, 0));

            // A back-dated loan may already be past installments or its end date: work out its
            // standing (and any penalties) straight away instead of waiting for the daily refresh.
            app(LoanLedger::class)->rebuildForLoan($loan->refresh());

            return $loan;
        });
    }

    /**
     * Correct the release date of a loan already released (e.g. a backlog loan entered with the
     * wrong date). Installment amounts stay the same; due dates move to run monthly from the new
     * release date, and penalties/status are recalculated.
     */
    public function changeDisbursementDate(Loan $loan, User $user, string $disbursedOn): Loan
    {
        if (! in_array($loan->status, [Loan::STATUS_DISBURSED, Loan::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['disbursed_on' => 'Only a released loan has a release date to correct.']);
        }

        $date = CarbonImmutable::parse($disbursedOn)->startOfDay();
        if ($date->toDateString() > now()->toDateString()) {
            throw ValidationException::withMessages(['disbursed_on' => 'The release date cannot be in the future.']);
        }

        return DB::transaction(function () use ($loan, $user, $date) {
            $old = $loan->disbursed_on?->format('j M Y');

            $this->moveEarlierStepsBack($loan, $date->setTime(12, 0));

            foreach ($loan->installments()->orderBy('number')->get() as $installment) {
                $installment->update(['due_date' => $date->addMonthsNoOverflow((int) $installment->number)->toDateString()]);
            }

            $loan->update(['disbursed_on' => $date->toDateString()]);

            LoanStatusHistory::where('loan_id', $loan->id)->where('to_status', Loan::STATUS_DISBURSED)
                ->get()->each(fn ($h) => $h->forceFill(['created_at' => $date->setTime(12, 0), 'updated_at' => $date->setTime(12, 0)])->saveQuietly());

            if ($old !== $date->format('j M Y')) {
                $this->recordHistory($loan, $loan->status, $loan->status, $user, 'Release date corrected from '.$old.' to '.$date->format('j M Y').'.');
            }

            app(LoanLedger::class)->rebuildForLoan($loan->refresh());

            return $loan;
        });
    }

    /**
     * Steps can't happen after the step that follows them: when a later step is dated earlier
     * (backlog entry), move the application and approval dates (and their history entries) back.
     */
    private function moveEarlierStepsBack(Loan $loan, CarbonImmutable $at): void
    {
        if ($loan->approved_at && $at->toDateString() < $loan->approved_at->toDateString()) {
            $loan->update(['approved_at' => $at]);
        }

        if ($loan->applied_at && $at->toDateString() < $loan->applied_at->toDateString()) {
            $loan->update(['applied_at' => $at]);
        }

        // Keep the timeline in order too: application and approval entries dated after this step.
        LoanStatusHistory::where('loan_id', $loan->id)
            ->where(fn ($q) => $q->whereNull('from_status')->orWhereIn('to_status', [Loan::STATUS_APPROVED, Loan::STATUS_PENDING_APPROVAL, Loan::STATUS_PENDING_GUARANTORS]))
            ->whereDate('created_at', '>', $at->toDateString())
            ->get()
            ->each(fn ($h) => $h->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly());
    }

    /**
     * A Loan Officer/Treasurer who is also a member cannot approve or disburse their own
     * loan, or one they are guaranteeing. Superusers are exempt and can act on any loan.
     */
    private function guardAgainstSelfDealing(Loan $loan, User $actingUser, string $action): void
    {
        if ($actingUser->hasRole('Superuser')) {
            return;
        }

        $actingMember = $actingUser->member;

        if ($actingMember === null) {
            return;
        }

        if ((int) $actingMember->id === (int) $loan->member_id) {
            throw ValidationException::withMessages(['status' => "You cannot {$action} your own loan."]);
        }

        if ($loan->guarantors()->where('guarantor_member_id', $actingMember->id)->exists()) {
            throw ValidationException::withMessages(['status' => "You cannot {$action} a loan you are guaranteeing."]);
        }
    }

    private function transition(Loan $loan, string $toStatus, ?User $actingUser, ?string $note): void
    {
        $fromStatus = $loan->status;
        $loan->update(['status' => $toStatus]);
        $this->recordHistory($loan, $fromStatus, $toStatus, $actingUser, $note);
    }

    /**
     * The date a workflow step happened: the chosen date for a back-dated entry (today's date
     * keeps the current time), or now.
     */
    private function workflowDate(?string $date): CarbonImmutable
    {
        if ($date === null || $date === '' || $date === now()->toDateString()) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::parse($date)->setTime(12, 0);
    }

    private function recordHistory(Loan $loan, ?string $fromStatus, string $toStatus, ?User $actingUser, ?string $note, ?CarbonImmutable $at = null): void
    {
        $history = LoanStatusHistory::create([
            'loan_id' => $loan->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actingUser?->id,
            'note' => $note,
        ]);

        if ($at !== null && ! $at->isSameDay(now())) {
            $history->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
    }
}
