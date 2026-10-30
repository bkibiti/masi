<?php

namespace Tests\Feature;

use App\Models\ContributionSetting;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\ContributionLedger;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LoanService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function loanType(array $overrides = []): LoanType
    {
        return LoanType::create([
            'name' => 'Standard',
            'interest_method' => LoanType::METHOD_MONTHLY_REDUCING,
            'interest_rate' => 2,
            'min_amount' => 50000,
            'max_amount' => 2000000,
            'min_duration_months' => 1,
            'max_duration_months' => 12,
            'penalty_percent' => 5,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /**
     * Gives the member exactly $amount of real, paid-up member_dues.amount_paid, via a real
     * contribution setting + payment + ledger rebuild (not hand-crafted rows).
     */
    private function contributionsOf(Member $member, float $amount): void
    {
        ContributionSetting::create([
            'name' => 'Test setting',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'monthly_amount' => $amount,
            'penalty_percent' => 0,
        ]);

        $member->created_at = '2025-01-01';
        $member->save();

        Payment::create([
            'member_id' => $member->id,
            'payment_method_id' => PaymentMethod::where('name', 'M-Koba')->value('id'),
            'payment_type_id' => PaymentType::where('name', 'Monthly Fee')->value('id'),
            'amount' => $amount,
            'paid_on' => '2025-01-15',
        ]);

        Carbon::setTestNow('2025-01-20');
        app(ContributionLedger::class)->rebuildForMember($member);
        Carbon::setTestNow();
    }

    public function test_c_a_member_with_an_active_loan_cannot_apply_for_another(): void
    {
        $member = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();

        $this->service->apply($member, $loanType, 100000, 6, null, [], $officer);

        $this->expectException(ValidationException::class);
        $this->service->apply($member, $loanType, 100000, 6, null, [], $officer);
    }

    public function test_d_an_amount_outside_the_loan_type_limits_is_rejected(): void
    {
        $member = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();

        $this->expectException(ValidationException::class);
        $this->service->apply($member, $loanType, 3_000_000, 6, null, [], $officer);
    }

    public function test_d_a_duration_outside_the_loan_type_limits_is_rejected(): void
    {
        $member = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();

        $this->expectException(ValidationException::class);
        $this->service->apply($member, $loanType, 100000, 24, null, [], $officer);
    }

    public function test_e_guarantors_are_not_required_at_or_below_80_percent_of_contributions(): void
    {
        $member = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();
        $this->contributionsOf($member, 1_000_000); // 80% limit = 800,000

        $loan = $this->service->apply($member, $loanType, 800000, 6, null, [], $officer);

        $this->assertSame(Loan::STATUS_PENDING_APPROVAL, $loan->status);
        $this->assertEquals(0, $loan->required_guarantee_amount);
    }

    public function test_e_guarantors_are_required_above_80_percent_and_approval_waits_for_coverage(): void
    {
        $member = Member::factory()->create();
        $guarantor = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();
        $this->contributionsOf($member, 1_000_000); // 80% limit = 800,000

        $loan = $this->service->apply($member, $loanType, 900000, 6, null, [
            ['guarantor_member_id' => $guarantor->id, 'guaranteed_amount' => 100000],
        ], $officer);

        $this->assertSame(Loan::STATUS_PENDING_GUARANTORS, $loan->status);
        $this->assertEquals(100000, $loan->required_guarantee_amount);

        // Guarantee not yet accepted: the loan cannot be approved.
        $this->expectException(ValidationException::class);
        $this->service->approve($loan, $officer);
    }

    public function test_e_accepting_guarantees_that_cover_the_required_amount_moves_the_loan_to_pending_approval(): void
    {
        $member = Member::factory()->create();
        $guarantor = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();
        $this->contributionsOf($member, 1_000_000);

        $loan = $this->service->apply($member, $loanType, 900000, 6, null, [
            ['guarantor_member_id' => $guarantor->id, 'guaranteed_amount' => 100000],
        ], $officer);

        $this->service->recordGuarantorResponse($loan->guarantors->first(), 'accepted', $officer);

        $this->assertSame(Loan::STATUS_PENDING_APPROVAL, $loan->refresh()->status);
    }

    public function test_e_a_decline_reverts_a_covered_loan_back_to_pending_guarantors(): void
    {
        $member = Member::factory()->create();
        $guarantorA = Member::factory()->create();
        $guarantorB = Member::factory()->create();
        $officer = User::factory()->create();
        $loanType = $this->loanType();
        $this->contributionsOf($member, 1_000_000);

        $loan = $this->service->apply($member, $loanType, 900000, 6, null, [
            ['guarantor_member_id' => $guarantorA->id, 'guaranteed_amount' => 100000],
        ], $officer);

        $this->service->addGuarantor($loan, $guarantorB->id, 100000);
        $guarantors = $loan->guarantors;

        $this->service->recordGuarantorResponse($guarantors[0], 'accepted', $officer);
        $this->assertSame(Loan::STATUS_PENDING_APPROVAL, $loan->refresh()->status);

        // The only accepted guarantor declines (edge case: re-recording their own response).
        $this->service->recordGuarantorResponse($guarantors[0]->refresh(), 'declined', $officer);
        $this->assertSame(Loan::STATUS_PENDING_GUARANTORS, $loan->refresh()->status);
    }
}
