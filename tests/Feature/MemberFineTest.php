<?php

namespace Tests\Feature;

use App\Models\ContributionSetting;
use App\Models\FineType;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberFine;
use App\Models\MemberPenalty;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\ContributionLedger;
use App\Services\PenaltyPaymentSplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MemberFineTest extends TestCase
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

    private function fineType(): FineType
    {
        return FineType::where('name', 'Meeting absence')->first();
    }

    private function fine(Member $member, string $finedOn, float $amount, ?FineType $type = null): MemberFine
    {
        return MemberFine::create([
            'member_id' => $member->id,
            'fine_type_id' => ($type ?? $this->fineType())->id,
            'fined_on' => $finedOn,
            'amount' => $amount,
            'reason' => 'Missed the monthly meeting',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function monthlyFeePayment(array $attributes): Payment
    {
        return Payment::create([
            ...$attributes,
            'payment_method_id' => PaymentMethod::where('name', 'M-Koba')->value('id'),
            'payment_type_id' => PaymentType::where('name', 'Monthly Fee')->value('id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function penaltyPayment(array $attributes): Payment
    {
        return Payment::create([
            ...$attributes,
            'payment_method_id' => PaymentMethod::where('name', 'M-Koba')->value('id'),
            'payment_type_id' => PaymentType::where('name', 'Fines / Penalties')->value('id'),
        ]);
    }

    public function test_a_manual_fine_is_not_removed_or_changed_by_contributions_refresh(): void
    {
        $member = Member::factory()->create();
        $fine = $this->fine($member, '2025-01-05', 5000);

        Artisan::call('contributions:refresh');

        $fine->refresh();
        $this->assertNotSoftDeleted($fine);
        $this->assertEquals(5000, $fine->amount);
        $this->assertEquals(0, $fine->amount_paid);
    }

    public function test_b_a_fines_payment_pays_the_oldest_fine_first_across_both_kinds(): void
    {
        $this->setting();
        $member = $this->memberSince('2025-01-10');

        // Jan is unpaid by its due date, so a 4,000 automatic penalty is charged 2025-02-01.
        // The manual fine, dated 2025-01-20, is older and must be paid first.
        $this->fine($member, '2025-01-20', 3000);

        Carbon::setTestNow('2025-02-10');
        $this->ledger->rebuildForMember($member);

        $this->penaltyPayment(['member_id' => $member->id, 'amount' => 5000, 'paid_on' => '2025-02-10']);
        $this->ledger->rebuildForMember($member);

        $fine = MemberFine::where('member_id', $member->id)->first();
        $penalty = MemberPenalty::where('member_id', $member->id)->first();

        $this->assertEquals(3000, $fine->amount_paid); // fully paid first
        $this->assertEquals(2000, $penalty->amount_paid); // remaining 2,000 of the 4,000 penalty
    }

    public function test_c_a_monthly_payment_made_while_a_manual_fine_is_unpaid_is_split(): void
    {
        // No automatic penalty here (penalty_percent 0): isolates the manual fine's effect.
        $this->setting(penaltyPercent: 0);
        $member = $this->memberSince('2025-01-10');
        $fine = $this->fine($member, '2025-01-15', 5000);

        $payment = $this->monthlyFeePayment(['member_id' => $member->id, 'amount' => 40000, 'paid_on' => '2025-02-05']);
        $this->ledger->rebuildForMember($payment->member);

        $split = app(PenaltyPaymentSplitter::class)->splitPayment($payment);

        $this->assertNotNull($split);
        $this->assertEquals(5000, $split['fine']);
        $this->assertEquals(35000, $split['fee']);

        $jan = MemberDue::where('member_id', $member->id)->whereDate('period', '2025-01-01')->first();
        $this->assertEquals(35000, $jan->amount_paid);
        $this->assertEquals(5000, $fine->fresh()->amount_paid);
    }

    public function test_d_no_penalty_is_ever_charged_on_an_unpaid_manual_fine(): void
    {
        $member = Member::factory()->create();
        $this->fine($member, '2024-01-05', 5000);

        Carbon::setTestNow('2026-06-01'); // more than a year of "unpaid" time
        $this->ledger->rebuildForMember($member);

        $this->assertSame(0, MemberPenalty::where('member_id', $member->id)->count());
        $this->assertEquals(5000, MemberFine::where('member_id', $member->id)->first()->amount);
    }

    public function test_e_editing_a_manual_fine_rebuilds_its_allocations(): void
    {
        $member = Member::factory()->create();
        $fine = $this->fine($member, '2025-01-01', 5000);
        $this->penaltyPayment(['member_id' => $member->id, 'amount' => 5000, 'paid_on' => '2025-01-10']);
        $this->ledger->rebuildForMember($member);

        $this->assertEquals(5000, $fine->fresh()->amount_paid);

        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Fines');

        $this->actingAs($admin)->put(route('member-fines.update', $fine), [
            'fine_type_id' => $fine->fine_type_id,
            'fined_on' => '2025-01-01',
            'amount' => 3000,
        ])->assertSessionHasNoErrors();

        $fine->refresh();
        $this->assertEquals(3000, $fine->amount);
        $this->assertEquals(3000, $fine->amount_paid); // the 5,000 payment now overpays; excess becomes credit
    }

    public function test_e_deleting_a_manual_fine_rebuilds_its_allocations(): void
    {
        $member = Member::factory()->create();
        $fine = $this->fine($member, '2025-01-01', 5000);
        $payment = $this->penaltyPayment(['member_id' => $member->id, 'amount' => 5000, 'paid_on' => '2025-01-10']);
        $this->ledger->rebuildForMember($member);

        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Fines');

        $this->actingAs($admin)->delete(route('member-fines.destroy', $fine))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($fine);
        $this->assertDatabaseMissing('payment_allocations', [
            'allocatable_type' => MemberFine::class,
            'allocatable_id' => $fine->id,
        ]);
        $this->assertNotNull($payment->fresh()); // untouched; still exists
    }

    public function test_f_a_member_sees_only_their_own_fines_in_my_account(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('Member Login');
        $me->user_id = $user->id;
        $me->save();

        $this->fine($me, '2025-01-05', 4000);
        $this->fine($other, '2025-01-05', 9000);
        $this->ledger->rebuildForMember($me);
        $this->ledger->rebuildForMember($other);

        $response = $this->actingAs($user)->get(route('account.statement'));

        $response->assertOk();
        $response->assertSee('4,000');
        $response->assertDontSee('9,000');
    }

    public function test_f_a_user_without_manage_fines_permission_cannot_add_a_fine(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('member-fines.store'), [
            'member_id' => $member->id,
            'fine_type_id' => $this->fineType()->id,
            'fined_on' => now()->toDateString(),
            'amount' => 1000,
        ])->assertForbidden();

        $this->assertSame(0, MemberFine::where('member_id', $member->id)->count());
    }

    public function test_adding_a_fine_always_takes_the_fine_types_default_amount(): void
    {
        $type = FineType::where('name', 'Meeting absence')->first();
        $type->update(['default_amount' => 7000]);
        $member = Member::factory()->create();
        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Fines');

        // Even if a tampered request posts a different amount, the type's default wins.
        $this->actingAs($admin)->post(route('member-fines.store'), [
            'member_id' => $member->id,
            'fine_type_id' => $type->id,
            'fined_on' => now()->toDateString(),
            'amount' => 999999,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('member_fines', ['member_id' => $member->id, 'amount' => 7000]);
    }

    public function test_adding_a_fine_is_rejected_when_the_fine_type_has_no_default_amount(): void
    {
        $type = $this->fineType(); // seeded with default_amount 0
        $member = Member::factory()->create();
        $admin = User::factory()->create();
        $admin->givePermissionTo('Manage Fines');

        $response = $this->actingAs($admin)->post(route('member-fines.store'), [
            'member_id' => $member->id,
            'fine_type_id' => $type->id,
            'fined_on' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('fine_type_id');
        $this->assertSame(0, MemberFine::where('member_id', $member->id)->count());
    }
}
