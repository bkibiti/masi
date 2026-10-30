<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentMethodAndTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(
            Permission::create(['name' => 'Manage Payment Methods', 'guard_name' => 'web']),
            Permission::create(['name' => 'Manage Payment Types', 'guard_name' => 'web']),
        );
    }

    private function payment(PaymentMethod $method, PaymentType $type): Payment
    {
        $member = Member::factory()->create();

        return Payment::create([
            'member_id' => $member->id,
            'payment_method_id' => $method->id,
            'payment_type_id' => $type->id,
            'amount' => 1000,
            'paid_on' => now()->toDateString(),
        ]);
    }

    public function test_a_method_used_on_a_payment_cannot_be_deleted(): void
    {
        $method = PaymentMethod::where('name', 'M-Koba')->first();
        $this->payment($method, PaymentType::where('name', 'Monthly Fee')->first());

        $this->actingAs($this->user)->delete(route('payment-methods.destroy', $method));

        $this->assertNotNull($method->fresh());
    }

    public function test_an_unused_method_can_be_deleted(): void
    {
        $method = PaymentMethod::create(['name' => 'Mobile Money', 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('payment-methods.destroy', $method));

        $this->assertNull($method->fresh());
    }

    public function test_a_type_used_on_a_payment_cannot_be_deleted(): void
    {
        $type = PaymentType::create(['name' => 'Donation', 'ledger_role' => 'none', 'is_active' => true]);
        $this->payment(PaymentMethod::where('name', 'M-Koba')->first(), $type);

        $this->actingAs($this->user)->delete(route('payment-types.destroy', $type));

        $this->assertNotNull($type->fresh());
    }

    public function test_a_system_type_cannot_be_deleted_even_when_unused(): void
    {
        $type = PaymentType::where('name', 'Fines / Penalties')->first();

        $this->actingAs($this->user)->delete(route('payment-types.destroy', $type));

        $this->assertNotNull($type->fresh());
    }

    public function test_a_system_types_ledger_role_cannot_be_changed(): void
    {
        $type = PaymentType::where('name', 'Monthly Fee')->first();

        $response = $this->actingAs($this->user)->put(route('payment-types.update', $type), [
            'name' => 'Monthly Fee (renamed)',
            'ledger_role' => 'none', // attempted change, should be ignored
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $type->refresh();
        $this->assertSame('Monthly Fee (renamed)', $type->name);
        $this->assertSame('monthly_fee', $type->ledger_role);
    }

    public function test_only_one_active_monthly_fee_type_is_allowed(): void
    {
        $response = $this->actingAs($this->user)->post(route('payment-types.store'), [
            'name' => 'Another Monthly Fee',
            'ledger_role' => 'monthly_fee',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('ledger_role');
        $this->assertSame(1, PaymentType::where('ledger_role', 'monthly_fee')->count());
    }

    public function test_at_most_one_active_penalty_type_is_allowed(): void
    {
        $response = $this->actingAs($this->user)->post(route('payment-types.store'), [
            'name' => 'Another Penalty Type',
            'ledger_role' => 'penalty',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('ledger_role');
        $this->assertSame(1, PaymentType::where('ledger_role', 'penalty')->where('is_active', true)->count());
    }

    public function test_an_inactive_second_penalty_type_is_allowed(): void
    {
        $response = $this->actingAs($this->user)->post(route('payment-types.store'), [
            'name' => 'Another Penalty Type',
            'ledger_role' => 'penalty',
            // is_active omitted -> false
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, PaymentType::where('name', 'Another Penalty Type')->count());
    }

    public function test_deactivating_the_only_active_monthly_fee_type_is_rejected(): void
    {
        $type = PaymentType::where('name', 'Monthly Fee')->first();

        $response = $this->actingAs($this->user)->put(route('payment-types.update', $type), [
            'name' => $type->name,
            'ledger_role' => 'monthly_fee',
            // is_active omitted -> false
        ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_the_payment_methods_index_renders(): void
    {
        $this->user->givePermissionTo(Permission::create(['name' => 'View Payment Methods', 'guard_name' => 'web']));

        $this->actingAs($this->user)->get(route('payment-methods.index'))->assertOk();
    }

    public function test_the_payment_types_index_renders(): void
    {
        $this->user->givePermissionTo(Permission::create(['name' => 'View Payment Types', 'guard_name' => 'web']));

        $this->actingAs($this->user)->get(route('payment-types.index'))->assertOk();
    }
}
