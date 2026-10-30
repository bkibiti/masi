<?php

namespace Tests\Feature;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\Payment;
use App\Models\User;
use App\Services\ContributionLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContributionSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(
            Permission::create(['name' => 'View Contribution Settings', 'guard_name' => 'web']),
            Permission::create(['name' => 'Manage Contribution Settings', 'guard_name' => 'web']),
        );
    }

    public function test_h_overlapping_settings_are_rejected(): void
    {
        ContributionSetting::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        $response = $this->actingAs($this->user)->post(route('contribution-settings.store'), [
            'name' => '2025 H2',
            'start_date' => '2025-04-01',
            'end_date' => '2025-09-30',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertSame(1, ContributionSetting::count());
    }

    public function test_h_a_setting_with_allocated_payments_is_locked(): void
    {
        $setting = ContributionSetting::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        $member = Member::factory()->create();
        $member->created_at = '2025-01-10';
        $member->save();
        Payment::create(['member_id' => $member->id, 'amount' => 40000, 'paid_on' => '2025-01-15']);

        app(ContributionLedger::class)->rebuildForMember($member);
        $this->assertTrue($setting->refresh()->isLocked());

        $response = $this->actingAs($this->user)->put(route('contribution-settings.update', $setting), [
            'name' => '2025 renamed',
            'start_date' => '2025-02-01', // attempted change, should be ignored
            'end_date' => '2025-12-31',
            'monthly_amount' => 99999, // attempted change, should be ignored
            'penalty_percent' => 50, // attempted change, should be ignored
        ]);

        $response->assertSessionHasNoErrors();
        $setting->refresh();
        $this->assertSame('2025 renamed', $setting->name);
        $this->assertSame('2025-01-01', $setting->start_date->toDateString());
        $this->assertEquals(40000, $setting->monthly_amount);
        $this->assertEquals(10, $setting->penalty_percent);
    }

    public function test_h_a_setting_with_dues_cannot_be_deleted(): void
    {
        $setting = ContributionSetting::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        $member = Member::factory()->create();
        $member->created_at = '2025-01-10';
        $member->save();

        MemberDue::create([
            'member_id' => $member->id,
            'contribution_setting_id' => $setting->id,
            'period' => '2025-01-01',
            'due_date' => '2025-01-31',
            'amount' => 40000,
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)->delete(route('contribution-settings.destroy', $setting));

        $this->assertSame(1, ContributionSetting::count());
    }
}
