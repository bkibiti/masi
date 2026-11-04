<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec (c): My Statement / My Loans show only the logged-in member's own data, never
 * another member's, regardless of what the URL says.
 */
class MyAccountScopingTest extends TestCase
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

    public function test_c_my_statement_shows_only_the_logged_in_members_payments(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        $user = $this->linkedUser($me);

        Payment::create([
            'member_id' => $me->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'payment_type_id' => PaymentType::where('name', 'Monthly Fee')->value('id'),
            'amount' => 12345,
            'paid_on' => now()->toDateString(),
            'reference' => 'MINE-REF',
        ]);
        Payment::create([
            'member_id' => $other->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'payment_type_id' => PaymentType::where('name', 'Monthly Fee')->value('id'),
            'amount' => 99999,
            'paid_on' => now()->toDateString(),
            'reference' => 'OTHER-REF',
        ]);

        $response = $this->actingAs($user)->get(route('account.statement'));

        $response->assertOk();
        $response->assertSee('MINE-REF');
        $response->assertDontSee('OTHER-REF');
    }

    public function test_c_my_loans_list_shows_only_the_logged_in_members_loans(): void
    {
        $me = Member::factory()->create(['name' => 'My Own Name']);
        $other = Member::factory()->create(['name' => 'Someone Else']);
        $user = $this->linkedUser($me);

        $this->loanFor($me);
        $this->loanFor($other);

        $response = $this->actingAs($user)->get(route('account.loans.index'));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('loans'));
    }

    public function test_c_viewing_another_members_loan_is_forbidden(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        $user = $this->linkedUser($me);

        $otherLoan = $this->loanFor($other);

        $response = $this->actingAs($user)->get(route('account.loans.show', $otherLoan));

        $response->assertForbidden();
    }

    public function test_c_cancelling_another_members_loan_is_forbidden(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        $user = $this->linkedUser($me);

        $otherLoan = $this->loanFor($other, Loan::STATUS_PENDING_APPROVAL);

        $response = $this->actingAs($user)->post(route('account.loans.cancel', $otherLoan));

        $response->assertForbidden();
        $this->assertSame(Loan::STATUS_PENDING_APPROVAL, $otherLoan->refresh()->status);
    }
}
