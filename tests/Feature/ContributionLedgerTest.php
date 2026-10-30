<?php

namespace Tests\Feature;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\Payment;
use App\Services\ContributionLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContributionLedgerTest extends TestCase
{
    use RefreshDatabase;

    private ContributionLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(ContributionLedger::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function setting(string $start = '2025-01-01', string $end = '2025-12-31', float $amount = 40000, float $penaltyPercent = 10): ContributionSetting
    {
        return ContributionSetting::create([
            'name' => $start.' - '.$end,
            'start_date' => $start,
            'end_date' => $end,
            'monthly_amount' => $amount,
            'penalty_percent' => $penaltyPercent,
        ]);
    }

    private function memberSince(string $createdAt): Member
    {
        $member = Member::factory()->create();
        $member->created_at = $createdAt;
        $member->save();

        return $member;
    }

    public function test_a_month_unpaid_by_month_end_gets_one_penalty_on_the_1st_of_the_next_month(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        Carbon::setTestNow('2025-02-15'); // January has fully ended; February hasn't.
        $this->ledger->rebuildForMember($member);

        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();

        $this->assertNotNull($due);
        $this->assertSame(0.0, (float) $due->amount_paid);
        $this->assertCount(1, $due->penalties);
        $this->assertSame('2025-02-01', $due->penalties->first()->charged_on->toDateString());
        $this->assertEquals(4000, $due->penalties->first()->amount); // 10% of 40,000
    }

    public function test_b_a_month_unpaid_for_3_month_ends_has_3_penalties(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        Carbon::setTestNow('2025-04-15'); // Jan, Feb and Mar have all fully ended.
        $this->ledger->rebuildForMember($member);

        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();

        $this->assertCount(3, $due->penalties);
        $this->assertSame(
            ['2025-02-01', '2025-03-01', '2025-04-01'],
            $due->penalties->pluck('charged_on')->map(fn ($d) => $d->toDateString())->all()
        );
    }

    public function test_c_penalty_is_a_percentage_of_the_outstanding_balance_after_a_partial_payment(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        Payment::create(['member_id' => $member->id, 'amount' => 15000, 'paid_on' => '2025-01-20']);

        Carbon::setTestNow('2025-02-10');
        $this->ledger->rebuildForMember($member);

        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();

        $this->assertEquals(15000, $due->amount_paid);
        $this->assertCount(1, $due->penalties);
        $this->assertEquals(2500, $due->penalties->first()->amount); // 10% of (40,000 - 15,000)
    }

    public function test_d_a_payment_clears_the_oldest_months_penalties_then_its_contribution_then_the_next_month(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        // No payments until March: Jan accrues 2 penalties (Feb 1, Mar 1), Feb accrues 1 (Mar 1).
        Payment::create(['member_id' => $member->id, 'amount' => 50000, 'paid_on' => '2025-03-10']);

        Carbon::setTestNow('2025-03-15');
        $this->ledger->rebuildForMember($member);

        $jan = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $feb = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-02-01')->first();

        // Jan: 2 penalties of 4,000 (8,000) + contribution 40,000 = 48,000, fully cleared.
        $this->assertEquals(40000, $jan->amount_paid);
        $this->assertTrue($jan->penalties->every(fn ($p) => (float) $p->amount_paid === (float) $p->amount));

        // Remaining 2,000 of the 50,000 goes to Feb's penalty, nothing to Feb's contribution yet.
        $this->assertEquals(0, $feb->amount_paid);
        $this->assertCount(1, $feb->penalties);
        $this->assertEquals(2000, $feb->penalties->first()->amount_paid);
        $this->assertEquals(4000, $feb->penalties->first()->amount);
    }

    public function test_e_an_overpayment_becomes_credit_and_is_applied_to_the_next_month(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        Payment::create(['member_id' => $member->id, 'amount' => 45000, 'paid_on' => '2025-01-15']);

        Carbon::setTestNow('2025-01-20'); // still January: no February due exists yet
        $this->ledger->rebuildForMember($member);
        $statement = $this->ledger->statementForMember($member, 2025);
        $this->assertEquals(5000, $statement['credit']);

        Carbon::setTestNow('2025-02-10'); // February's due now exists; the daily refresh would run this
        $this->ledger->rebuildForMember($member);

        $feb = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-02-01')->first();
        $this->assertEquals(5000, $feb->amount_paid);

        $statement = $this->ledger->statementForMember($member, 2025);
        $this->assertEquals(0, $statement['credit']);
    }

    public function test_f_a_back_dated_payment_removes_the_penalty_after_a_rebuild(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        Carbon::setTestNow('2025-02-15');
        $this->ledger->rebuildForMember($member);
        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $this->assertCount(1, $due->penalties);

        // A payment is now entered, dated before January's due date.
        Payment::create(['member_id' => $member->id, 'amount' => 40000, 'paid_on' => '2025-01-25']);
        $this->ledger->rebuildForMember($member);

        // The rebuild deletes and recreates every due/penalty row, so re-fetch rather than refresh().
        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $this->assertEquals(40000, $due->amount_paid);
        $this->assertCount(0, $due->penalties()->get());
    }

    public function test_g_deleting_a_payment_rebuilds_the_ledger_correctly(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        $payment = Payment::create(['member_id' => $member->id, 'amount' => 40000, 'paid_on' => '2025-01-15']);

        Carbon::setTestNow('2025-02-15');
        $this->ledger->rebuildForMember($member);
        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $this->assertEquals(40000, $due->amount_paid);
        $this->assertCount(0, $due->penalties);

        $payment->delete();
        $this->ledger->rebuildForMember($member);

        $due = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $this->assertEquals(0, $due->amount_paid);
        $this->assertCount(1, $due->penalties);
    }

    public function test_j_running_the_refresh_command_twice_changes_nothing(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');
        Payment::create(['member_id' => $member->id, 'amount' => 45000, 'paid_on' => '2025-01-15']);

        Carbon::setTestNow('2025-03-10');

        Artisan::call('contributions:refresh');
        $snapshot1 = MemberDue::query()->orderBy('id')->get(['member_id', 'period', 'amount', 'amount_paid'])->toArray();

        Artisan::call('contributions:refresh');
        $snapshot2 = MemberDue::query()->orderBy('id')->get(['member_id', 'period', 'amount', 'amount_paid'])->toArray();

        $this->assertSame($snapshot1, $snapshot2);
    }
}
