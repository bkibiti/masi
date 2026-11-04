<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Spec (e): a Loan Officer cannot approve/reject their own loan, and a Treasurer cannot
 * disburse their own loan - the same restriction applies to a loan either is guaranteeing.
 */
class LoanSelfDealingTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LoanService::class);
    }

    private ?LoanType $loanType = null;

    private function loanType(): LoanType
    {
        return $this->loanType ??= LoanType::create([
            'name' => 'Standard',
            'interest_method' => LoanType::METHOD_FIXED,
            'interest_rate' => 5,
            'min_amount' => 1000,
            'max_amount' => 1_000_000,
            'min_duration_months' => 1,
            'max_duration_months' => 12,
            'penalty_percent' => 5,
            'is_active' => true,
        ]);
    }

    private function pendingApprovalLoanFor(Member $member): Loan
    {
        return Loan::create([
            'member_id' => $member->id,
            'loan_type_id' => $this->loanType()->id,
            'requested_amount' => 100000,
            'requested_duration_months' => 6,
            'interest_method' => LoanType::METHOD_FIXED,
            'interest_rate' => 5,
            'penalty_percent' => 5,
            'status' => Loan::STATUS_PENDING_APPROVAL,
            'applicant_contributions_total' => 0,
            'required_guarantee_amount' => 0,
            'applied_at' => now(),
        ]);
    }

    private function linkedUser(Member $member, string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $member->user_id = $user->id;
        $member->save();

        return $user;
    }

    public function test_e_a_loan_officer_cannot_approve_their_own_loan(): void
    {
        $officerMember = Member::factory()->create();
        $officer = $this->linkedUser($officerMember, 'Loan Officer');
        $loan = $this->pendingApprovalLoanFor($officerMember);

        $this->expectException(ValidationException::class);

        $this->service->approve($loan, $officer);
    }

    public function test_e_a_loan_officer_cannot_approve_a_loan_they_are_guaranteeing(): void
    {
        $officerMember = Member::factory()->create();
        $officer = $this->linkedUser($officerMember, 'Loan Officer');
        $applicant = Member::factory()->create();
        $loan = $this->pendingApprovalLoanFor($applicant);

        LoanGuarantor::create([
            'loan_id' => $loan->id,
            'guarantor_member_id' => $officerMember->id,
            'guaranteed_amount' => 20000,
            'status' => LoanGuarantor::STATUS_ACCEPTED,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->approve($loan, $officer);
    }

    public function test_e_this_restriction_surfaces_as_a_redirect_with_a_session_error_over_http(): void
    {
        $officerMember = Member::factory()->create();
        $officer = $this->linkedUser($officerMember, 'Loan Officer');
        $loan = $this->pendingApprovalLoanFor($officerMember);

        $response = $this->actingAs($officer)->post(route('loans.approve', $loan));

        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
        $this->assertSame(Loan::STATUS_PENDING_APPROVAL, $loan->refresh()->status);
    }

    public function test_e_a_treasurer_cannot_disburse_their_own_loan(): void
    {
        $treasurerMember = Member::factory()->create();
        $treasurer = $this->linkedUser($treasurerMember, 'Treasurer');
        $loan = $this->pendingApprovalLoanFor($treasurerMember);
        $loan->update(['status' => Loan::STATUS_APPROVED, 'approved_amount' => 100000, 'approved_duration_months' => 6]);

        $this->expectException(ValidationException::class);

        $this->service->disburse($loan, $treasurer, now()->toDateString(), PaymentMethod::first()->id, null, null);
    }

    public function test_e_a_treasurer_cannot_disburse_a_loan_they_are_guaranteeing(): void
    {
        $treasurerMember = Member::factory()->create();
        $treasurer = $this->linkedUser($treasurerMember, 'Treasurer');
        $applicant = Member::factory()->create();
        $loan = $this->pendingApprovalLoanFor($applicant);
        $loan->update(['status' => Loan::STATUS_APPROVED, 'approved_amount' => 100000, 'approved_duration_months' => 6]);

        LoanGuarantor::create([
            'loan_id' => $loan->id,
            'guarantor_member_id' => $treasurerMember->id,
            'guaranteed_amount' => 20000,
            'status' => LoanGuarantor::STATUS_ACCEPTED,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->disburse($loan, $treasurer, now()->toDateString(), PaymentMethod::first()->id, null, null);
    }

    public function test_a_superuser_can_approve_and_disburse_their_own_loan(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('Superuser', 'web');

        $member = Member::factory()->create();
        $superuser = $this->linkedUser($member, 'Superuser');
        $loan = $this->pendingApprovalLoanFor($member);

        $this->service->approve($loan, $superuser);
        $this->assertSame(Loan::STATUS_APPROVED, $loan->refresh()->status);

        $this->service->disburse($loan, $superuser, now()->toDateString(), PaymentMethod::first()->id, null, null);
        $this->assertSame(Loan::STATUS_DISBURSED, $loan->refresh()->status);
    }
}
