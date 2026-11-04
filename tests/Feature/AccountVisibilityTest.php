<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec (g): a staff user with no linked member doesn't see "My Account", and every "My
 * Account" route refuses them.
 */
class AccountVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_g_a_staff_user_without_a_linked_member_does_not_see_my_account_in_the_menu(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('View Dashboard');

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('My Account');
    }

    public function test_g_a_staff_user_without_a_linked_member_is_forbidden_from_my_statement(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.statement'));

        $response->assertForbidden();
    }

    public function test_g_a_staff_user_without_a_linked_member_is_forbidden_from_my_loans(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.loans.index'));

        $response->assertForbidden();
    }
}
