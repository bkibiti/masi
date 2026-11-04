<?php

namespace Tests\Feature;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $dashboard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboard = app(DashboardService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payment(string $typeName, float $amount, string $paidOn): Payment
    {
        $member = Member::factory()->create();
        $method = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'M-Koba', 'is_active' => true]);
        $type = PaymentType::where('name', $typeName)->first();

        return Payment::create([
            'member_id' => $member->id,
            'payment_method_id' => $method->id,
            'payment_type_id' => $type->id,
            'amount' => $amount,
            'paid_on' => $paidOn,
        ]);
    }

    private function due(Member $member, string $period, float $amount, float $amountPaid, string $dueDate): MemberDue
    {
        $setting = ContributionSetting::first() ?? ContributionSetting::create([
            'name' => 'Test setting',
            'start_date' => '2024-01-01',
            'end_date' => '2026-12-31',
            'monthly_amount' => $amount,
            'penalty_percent' => 10,
        ]);

        return MemberDue::create([
            'member_id' => $member->id,
            'contribution_setting_id' => $setting->id,
            'period' => $period,
            'due_date' => $dueDate,
            'amount' => $amount,
            'amount_paid' => $amountPaid,
        ]);
    }

    public function test_a_total_received_includes_all_payment_types_and_only_the_selected_year(): void
    {
        $this->payment('Monthly Fee', 50000, '2025-03-10');
        $this->payment('Fines / Penalties', 4000, '2025-06-05');
        $this->payment('Loan Repayment', 20000, '2025-09-01');
        $this->payment('Monthly Fee', 99999, '2024-01-01'); // different year, must be excluded

        $totals = $this->dashboard->totalsCard(2025);

        $this->assertEquals(74000, $totals['total']);
    }

    public function test_b_monthly_fees_collected_and_rate_count_only_closed_months(): void
    {
        Carbon::setTestNow('2025-06-15');
        $member = Member::factory()->create();

        // Closed month (due date already passed): fully paid.
        $this->due($member, '2025-01-01', 40000, 40000, '2025-01-31');
        // Closed month: half paid.
        $this->due($member, '2025-02-01', 40000, 20000, '2025-02-28');
        // Not yet closed (due date in the future): unpaid, must not affect the rate.
        $this->due($member, '2025-07-01', 40000, 0, '2025-07-31');

        $result = $this->dashboard->monthlyFeesCard(2025);

        // Collected sums every month in the year, closed or not.
        $this->assertEquals(60000, $result['collected']);
        // Rate: (40000 + 20000) / (40000 + 40000) = 75%, from the two closed months only.
        $this->assertSame(75, $result['rate']);
        $this->assertSame('Jan–Feb', $result['range_label']);
    }

    public function test_b_no_closed_months_reports_a_null_rate(): void
    {
        Carbon::setTestNow('2025-06-15');
        $member = Member::factory()->create();
        $this->due($member, '2025-07-01', 40000, 0, '2025-07-31'); // due date is in the future

        $result = $this->dashboard->monthlyFeesCard(2025);

        $this->assertNull($result['rate']);
    }

    public function test_c_arrears_is_unpaid_fees_past_due_plus_unpaid_penalties_regardless_of_year(): void
    {
        Carbon::setTestNow('2026-01-10');
        $member = Member::factory()->create();

        $this->due($member, '2024-01-01', 40000, 15000, '2024-01-31'); // past due, 25,000 outstanding
        $this->due($member, '2026-02-01', 40000, 0, '2026-02-28'); // not yet due, excluded

        $due = $this->due($member, '2024-02-01', 40000, 40000, '2024-02-29');
        MemberPenalty::create([
            'member_id' => $member->id,
            'member_due_id' => $due->id,
            'charged_on' => '2024-03-01',
            'amount' => 4000,
            'amount_paid' => 1000,
        ]);

        $arrears = $this->dashboard->arrearsCard();

        $this->assertEquals(25000, $arrears['fees']);
        $this->assertEquals(3000, $arrears['penalties']);
        $this->assertEquals(28000, $arrears['total']);
    }

    public function test_d_chart_values_per_month_match_member_dues_sums(): void
    {
        $member1 = Member::factory()->create();
        $member2 = Member::factory()->create();

        $this->due($member1, '2025-03-01', 40000, 40000, '2025-03-31');
        $this->due($member2, '2025-03-01', 50000, 10000, '2025-03-31');

        $chart = $this->dashboard->monthlyChart(2025);
        $march = collect($chart['months'])->firstWhere('month', 3);
        $april = collect($chart['months'])->firstWhere('month', 4);

        $this->assertTrue($march['has_dues']);
        $this->assertEquals(90000, $march['expected']);
        $this->assertEquals(50000, $march['collected']);
        $this->assertSame(56, $march['percent']); // round(50000/90000*100)

        $this->assertFalse($april['has_dues']);
        $this->assertEquals(0, $april['expected']);
    }

    public function test_e_current_month_panel_counts_fully_paid_members_correctly(): void
    {
        Carbon::setTestNow('2025-06-10');
        ContributionSetting::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        $paid = Member::factory()->create();
        $unpaid = Member::factory()->create();
        $this->due($paid, '2025-06-01', 40000, 40000, '2025-06-30');
        $this->due($unpaid, '2025-06-01', 40000, 0, '2025-06-30');

        $panel = $this->dashboard->currentMonthPanel();

        $this->assertSame(2, $panel['total_members']);
        $this->assertSame(1, $panel['paid_members']);
        $this->assertSame(1, $panel['unpaid_members']);
        $this->assertSame(50, $panel['percent']);
        $this->assertNotNull($panel['setting']);
        $this->assertSame('Due in 20 days', $panel['due_badge']);
    }

    public function test_e_current_month_panel_handles_no_active_setting(): void
    {
        Carbon::setTestNow('2025-06-10');

        $panel = $this->dashboard->currentMonthPanel();

        $this->assertNull($panel['setting']);
        $this->assertNull($panel['penalty_amount']);
    }

    public function test_f_recent_payments_shows_the_latest_10_newest_first(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->payment('Monthly Fee', 1000 * $i, sprintf('2025-01-%02d', $i));
        }

        $recent = $this->dashboard->recentPayments();

        $this->assertCount(10, $recent);
        $this->assertSame('2025-01-15', $recent->first()->paid_on->toDateString());
        $this->assertSame('2025-01-06', $recent->last()->paid_on->toDateString());
    }

    /**
     * Every role now holds "View Dashboard" (spec: linking members and users, section 4), so
     * /home no longer gates the group dashboard behind a permission check - any logged-in
     * user sees it.
     */
    public function test_g_every_logged_in_user_sees_the_group_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Monthly fees: collected vs expected');
    }

    public static function namedRolesProvider(): array
    {
        return [
            'Member Login' => ['Member Login'],
            'Loan Officer' => ['Loan Officer'],
            'Treasurer' => ['Treasurer'],
        ];
    }

    /**
     * Spec (a): every role can view the dashboard - checked here for the three roles this
     * task seeds. The migration also grants it to the pre-existing Superuser/Manager/Partners
     * roles, which are not seeded in the test schema (see AppServiceProvider).
     */
    #[DataProvider('namedRolesProvider')]
    public function test_a_a_user_with_a_seeded_role_sees_the_group_dashboard(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Monthly fees: collected vs expected');
    }
}
