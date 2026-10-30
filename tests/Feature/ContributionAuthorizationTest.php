<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Scenario (i) from the spec is "a member sees only their own contributions; admin routes
 * return 403 for members." This app has no member portal/login (see the Step 1 plan), so the
 * closest faithful check is permission-based: a signed-in User without the Contributions
 * permissions is refused the admin screens, exactly as any other User-gated feature here.
 */
class ContributionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_i_a_user_without_permission_is_forbidden_from_the_register(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('contributions.register'));

        $response->assertForbidden();
    }

    public function test_i_a_user_without_permission_is_forbidden_from_the_payments_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('contributions.payments.index'));

        $response->assertForbidden();
    }

    public function test_i_a_user_without_permission_cannot_record_a_payment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('contributions.payments.store'), [
            'member_id' => 1,
            'amount' => 1000,
            'paid_on' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_i_a_user_with_permission_can_view_the_register(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'View Contributions', 'guard_name' => 'web']));

        $response = $this->actingAs($user)->get(route('contributions.register'));

        $response->assertOk();
    }

    public function test_the_payments_list_renders_with_the_record_and_edit_payment_forms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            Permission::create(['name' => 'View Contributions', 'guard_name' => 'web']),
            Permission::create(['name' => 'Record Payments', 'guard_name' => 'web']),
            Permission::create(['name' => 'Manage Payments', 'guard_name' => 'web']),
        );

        $response = $this->actingAs($user)->get(route('contributions.payments.index'));

        $response->assertOk();
        $response->assertSee('Payment type');
        $response->assertSee('Payment method');
    }
}
