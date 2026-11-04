<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec (d): a member can apply for a loan, cancel their own pending application, and
 * accept/decline only guarantee requests addressed to them.
 */
class MyLoanSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function linkedUser(Member $member): User
    {
        $user = User::factory()->create();
        $user->assignRole('Member Login');
        $member->user_id = $user->id;
        $member->save();

        return $user;
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

    private function loanFor(Member $member, string $status = Loan::STATUS_PENDING_APPROVAL): Loan
    {
        return Loan::create([
            'member_id' => $member->id,
            'loan_type_id' => $this->loanType()->id,
            'requested_amount' => 50000,
            'requested_duration_months' => 3,
            'interest_method' => LoanType::METHOD_FIXED,
            'interest_rate' => 5,
            'penalty_percent' => 5,
            'status' => $status,
            'applicant_contributions_total' => 0,
            'required_guarantee_amount' => 0,
            'applied_at' => now(),
        ]);
    }

    public function test_d_a_member_can_apply_for_a_loan_naming_a_guarantor(): void
    {
        $me = Member::factory()->create();
        $guarantor = Member::factory()->create();
        $user = $this->linkedUser($me);
        $type = $this->loanType();

        $response = $this->actingAs($user)->post(route('account.loans.store'), [
            'loan_type_id' => $type->id,
            'requested_amount' => 100000,
            'requested_duration_months' => 6,
            'guarantors' => [
                ['guarantor_member_id' => $guarantor->id, 'guaranteed_amount' => 100000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loans', ['member_id' => $me->id, 'requested_amount' => 100000]);
    }

    public function test_d_a_member_cannot_name_themselves_as_their_own_guarantor(): void
    {
        $me = Member::factory()->create();
        $user = $this->linkedUser($me);
        $type = $this->loanType();

        $response = $this->actingAs($user)->post(route('account.loans.store'), [
            'loan_type_id' => $type->id,
            'requested_amount' => 100000,
            'requested_duration_months' => 6,
            'guarantors' => [
                ['guarantor_member_id' => $me->id, 'guaranteed_amount' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('guarantors.0.guarantor_member_id');
        $this->assertDatabaseMissing('loans', ['member_id' => $me->id]);
    }

    public function test_d_a_member_can_cancel_their_own_pending_application(): void
    {
        $me = Member::factory()->create();
        $user = $this->linkedUser($me);
        $loan = $this->loanFor($me, Loan::STATUS_PENDING_APPROVAL);

        $response = $this->actingAs($user)->post(route('account.loans.cancel', $loan));

        $response->assertRedirect(route('account.loans.index'));
        $this->assertSame(Loan::STATUS_CANCELLED, $loan->refresh()->status);
    }

    public function test_d_a_member_can_accept_a_guarantee_request_addressed_to_them(): void
    {
        $applicant = Member::factory()->create();
        $me = Member::factory()->create();
        $user = $this->linkedUser($me);
        $loan = $this->loanFor($applicant, Loan::STATUS_PENDING_GUARANTORS);

        $guarantee = LoanGuarantor::create([
            'loan_id' => $loan->id,
            'guarantor_member_id' => $me->id,
            'guaranteed_amount' => 20000,
            'status' => LoanGuarantor::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post(route('account.guarantees.accept', $guarantee));

        $response->assertRedirect();
        $this->assertSame(LoanGuarantor::STATUS_ACCEPTED, $guarantee->refresh()->status);
    }

    public function test_d_a_member_cannot_respond_to_another_members_guarantee_request(): void
    {
        $applicant = Member::factory()->create();
        $intendedGuarantor = Member::factory()->create();
        $me = Member::factory()->create();
        $user = $this->linkedUser($me);
        $loan = $this->loanFor($applicant, Loan::STATUS_PENDING_GUARANTORS);

        $guarantee = LoanGuarantor::create([
            'loan_id' => $loan->id,
            'guarantor_member_id' => $intendedGuarantor->id,
            'guaranteed_amount' => 20000,
            'status' => LoanGuarantor::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post(route('account.guarantees.accept', $guarantee));

        $response->assertForbidden();
        $this->assertSame(LoanGuarantor::STATUS_PENDING, $guarantee->refresh()->status);
    }
}
