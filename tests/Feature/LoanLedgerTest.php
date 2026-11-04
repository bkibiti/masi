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
use App\Services\LoanLedger;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanLedgerTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loanService;

    private LoanLedger $loanLedger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loanService = app(LoanService::class);
        $this->loanLedger = app(LoanLedger::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    /**
     * A disbursed 2-installment, 0% interest, 10%/month-penalty loan released 2026-01-10:
     * TZS 50,000 due on 2026-02-10 and 2026-03-10 (period ends 2026-03-10).
     */
    private function disbursedLoan(): Loan
    {
        $member = Member::factory()->create();
        $officer = User::factory()->create();
        $this->contributionsOf($member, 1_000_000); // keeps the 80% threshold well above 100,000

        $loanType = LoanType::create([
            'name' => 'Standard',
            'interest_method' => LoanType::METHOD_FIXED,
            'interest_rate' => 0,
            'min_amount' => 1000,
            'max_amount' => 1_000_000,
            'min_duration_months' => 1,
            'max_duration_months' => 12,
            'penalty_percent' => 10,
            'is_active' => true,
        ]);

        $loan = $this->loanService->apply($member, $loanType, 100000, 2, null, [], $officer);
        $this->loanService->approve($loan, $officer);
        $this->loanService->disburse($loan, $officer, '2026-01-10', PaymentMethod::first()->id, null, null);

        return $loan->refresh();
    }

    private function repay(Loan $loan, float $amount, string $paidOn): Payment
    {
        $payment = Payment::create([
            'member_id' => $loan->member_id,
            'payment_method_id' => PaymentMethod::first()->id,
            'payment_type_id' => PaymentType::where('name', 'Loan Repayment')->value('id'),
            'loan_id' => $loan->id,
            'amount' => $amount,
            'paid_on' => $paidOn,
        ]);

        $this->loanLedger->rebuildForLoan($loan);

        return $payment;
    }

    public function test_h_no_penalty_is_charged_while_an_installment_is_late_during_the_loan_period(): void
    {
        $loan = $this->disbursedLoan();

        Carbon::setTestNow('2026-03-05'); // installment 1 (due 2026-02-10) is overdue, but the period ends 2026-03-10
        $this->loanLedger->rebuildForLoan($loan);

        $this->assertCount(0, $loan->penalties()->get());
    }

    public function test_i_a_monthly_penalty_is_charged_each_month_after_the_period_ends_until_paid(): void
    {
        $loan = $this->disbursedLoan();

        Carbon::setTestNow('2026-04-20'); // period ended 2026-03-10: penalties on 2026-03-11 and 2026-04-11
        $this->loanLedger->rebuildForLoan($loan);

        $penalties = $loan->penalties()->orderBy('charged_on')->get();

        $this->assertCount(2, $penalties);
        $this->assertSame('2026-03-11', $penalties[0]->charged_on->toDateString());
        $this->assertEquals(10000, $penalties[0]->amount); // 10% of the unpaid 100,000 installment balance
        $this->assertSame('2026-04-11', $penalties[1]->charged_on->toDateString());
        // The base excludes the unpaid 2026-03-11 penalty itself (rule 5), so it stays 10,000, not 11,000.
        $this->assertEquals(10000, $penalties[1]->amount);
    }

    public function test_j_a_repayment_clears_penalties_oldest_first_before_any_installment(): void
    {
        $loan = $this->disbursedLoan();

        Carbon::setTestNow('2026-04-20');
        $this->loanLedger->rebuildForLoan($loan);

        $this->repay($loan, 15000, '2026-04-25');

        $penalties = $loan->penalties()->orderBy('charged_on')->get();
        $this->assertEquals(10000, $penalties[0]->amount_paid); // fully paid
        $this->assertEquals(5000, $penalties[1]->amount_paid); // partly paid

        $installments = $loan->installments()->orderBy('number')->get();
        $this->assertEquals(0, $installments[0]->amount_paid);
        $this->assertEquals(0, $installments[1]->amount_paid);
    }

    public function test_j_a_repayment_then_clears_installments_oldest_first(): void
    {
        $loan = $this->disbursedLoan();

        Carbon::setTestNow('2026-04-20');
        $this->loanLedger->rebuildForLoan($loan);

        // Clears both penalties (20,000) and all of installment 1 (50,000), 10,000 into installment 2.
        $this->repay($loan, 80000, '2026-04-25');

        $installments = $loan->installments()->orderBy('number')->get();
        $this->assertEquals(50000, $installments[0]->amount_paid);
        $this->assertEquals(10000, $installments[1]->amount_paid);
    }

    public function test_j_an_overpayment_beyond_the_loan_balance_is_rejected(): void
    {
        $loan = $this->disbursedLoan();

        $this->expectException(ValidationException::class);
        $this->loanLedger->validateNewPaymentAmount($loan, 100001);
    }

    public function test_j_the_loan_completes_automatically_once_fully_paid(): void
    {
        $loan = $this->disbursedLoan();

        $this->repay($loan, 100000, '2026-02-20');

        $this->assertSame(Loan::STATUS_COMPLETED, $loan->refresh()->status);
        $this->assertNotNull($loan->completed_at);
    }

    public function test_k_a_loan_repayment_never_touches_contributions(): void
    {
        $loan = $this->disbursedLoan();
        $member = $loan->member;

        $contributionsBefore = $member->totalContributionsPaid();

        $this->repay($loan, 50000, '2026-02-20');

        $this->assertEquals($contributionsBefore, $member->totalContributionsPaid());
        $this->assertSame(0, Payment::ledgerRelevant()->where('loan_id', $loan->id)->count());
    }

    public function test_m_running_loans_refresh_twice_changes_nothing(): void
    {
        $loan = $this->disbursedLoan();
        $this->repay($loan, 30000, '2026-02-20');

        Carbon::setTestNow('2026-05-15');

        Artisan::call('loans:refresh');
        $snapshot1 = [
            'installments' => $loan->installments()->orderBy('number')->get(['amount_paid'])->toArray(),
            'penalties' => $loan->penalties()->orderBy('charged_on')->get(['charged_on', 'amount', 'amount_paid'])->toArray(),
            'status' => $loan->refresh()->status,
        ];

        Artisan::call('loans:refresh');
        $snapshot2 = [
            'installments' => $loan->installments()->orderBy('number')->get(['amount_paid'])->toArray(),
            'penalties' => $loan->penalties()->orderBy('charged_on')->get(['charged_on', 'amount', 'amount_paid'])->toArray(),
            'status' => $loan->refresh()->status,
        ];

        $this->assertSame($snapshot1, $snapshot2);
    }
}
