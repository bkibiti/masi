<?php

namespace Tests\Feature;

use App\Models\LoanType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Scenario (l) from the spec is "members only see their own loans and their own guarantee
 * requests." This app has no member portal/login (see the Step 1 plan, confirmed staff-only
 * for V1), so -- exactly as ContributionAuthorizationTest does for contributions -- the
 * faithful check here is permission-based: a signed-in User without the Loans permissions is
 * refused the staff screens.
 */
class LoanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_a_user_without_permission_is_forbidden_from_the_loans_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('loans.index'));

        $response->assertForbidden();
    }

    public function test_l_a_user_without_permission_cannot_apply_for_a_loan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('loans.create'));

        $response->assertForbidden();
    }

    public function test_l_having_an_unrelated_loans_permission_does_not_grant_view_loans(): void
    {
        LoanType::create([
            'name' => 'Standard',
            'interest_method' => LoanType::METHOD_FIXED,
            'interest_rate' => 5,
            'min_amount' => 1000,
            'max_amount' => 1_000_000,
            'min_duration_months' => 1,
            'max_duration_months' => 12,
            'penalty_percent' => 5,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'View Loan Types')->first());

        $response = $this->actingAs($user)->get(route('loan-types.index'));
        $response->assertOk();

        $response = $this->actingAs($user)->get(route('loans.index'));
        $response->assertForbidden();
    }

    public function test_l_a_user_with_view_loans_permission_can_view_the_loans_list(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'View Loans')->first());

        $response = $this->actingAs($user)->get(route('loans.index'));

        $response->assertOk();
    }
}
