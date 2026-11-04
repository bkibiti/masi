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
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
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

    private function loanType(): LoanType
    {
        return LoanType::create([
            'name' => 'Standard',
            'interest_method' => LoanType::METHOD_MONTHLY_REDUCING,
            'interest_rate' => 2,
            'min_amount' => 50000,
            'max_amount' => 2_000_000,
            'min_duration_months' => 1,
            'max_duration_months' => 12,
            'penalty_percent' => 5,
            'is_active' => true,
        ]);
    }

    /**
     * Enough paid-up contributions that the loan amount used in these tests never crosses
     * the 80% guarantor threshold, so the application lands straight in pending_approval.
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

    private function pendingApprovalLoan(): Loan
    {
        $member = Member::factory()->create();
        $this->contributionsOf($member, 1_000_000);
        $officer = User::factory()->create();

        return $this->service->apply($member, $this->loanType(), 100000, 6, null, [], $officer);
    }

    public function test_f_only_a_loan_officer_can_approve(): void
    {
        $loan = $this->pendingApprovalLoan();

        $plainUser = User::factory()->create();
        $this->actingAs($plainUser)->post(route('loans.approve', $loan))->assertForbidden();

        $officer = User::factory()->create();
        $officer->assignRole('Loan Officer');
        $this->actingAs($officer)->post(route('loans.approve', $loan))->assertRedirect();

        $this->assertSame(Loan::STATUS_APPROVED, $loan->refresh()->status);
    }

    public function test_f_only_a_loan_officer_can_reject(): void
    {
        $loan = $this->pendingApprovalLoan();

        $plainUser = User::factory()->create();
        $this->actingAs($plainUser)->post(route('loans.reject', $loan), ['rejection_reason' => 'Not eligible'])->assertForbidden();

        $officer = User::factory()->create();
        $officer->assignRole('Loan Officer');
        $this->actingAs($officer)->post(route('loans.reject', $loan), ['rejection_reason' => 'Not eligible'])->assertRedirect();

        $this->assertSame(Loan::STATUS_REJECTED, $loan->refresh()->status);
    }

    public function test_f_only_a_treasurer_can_disburse(): void
    {
        $loan = $this->pendingApprovalLoan();
        $officer = User::factory()->create();
        $officer->assignRole('Loan Officer');
        $this->service->approve($loan, $officer);

        $method = PaymentMethod::first();

        $plainUser = User::factory()->create();
        $this->actingAs($plainUser)->post(route('loans.disburse', $loan), [
            'disbursed_on' => now()->toDateString(),
            'payment_method_id' => $method->id,
        ])->assertForbidden();

        $treasurer = User::factory()->create();
        $treasurer->assignRole('Treasurer');
        $this->actingAs($treasurer)->post(route('loans.disburse', $loan), [
            'disbursed_on' => now()->toDateString(),
            'payment_method_id' => $method->id,
        ])->assertRedirect();

        $this->assertSame(Loan::STATUS_DISBURSED, $loan->refresh()->status);
    }

    public function test_g_disbursement_creates_the_schedule_with_monthly_due_dates_from_the_release_date(): void
    {
        $loan = $this->pendingApprovalLoan();
        $officer = User::factory()->create();
        $officer->assignRole('Loan Officer');
        $this->service->approve($loan, $officer);

        $treasurer = User::factory()->create();
        $this->service->disburse($loan, $treasurer, '2026-01-15', PaymentMethod::first()->id, null, null);

        $installments = $loan->installments()->orderBy('number')->get();

        $this->assertCount(6, $installments);
        $this->assertSame('2026-02-15', $installments[0]->due_date->toDateString());
        $this->assertSame('2026-03-15', $installments[1]->due_date->toDateString());
        $this->assertSame('2026-07-15', $installments[5]->due_date->toDateString());
        $this->assertSame(Loan::STATUS_DISBURSED, $loan->refresh()->status);
    }
}
