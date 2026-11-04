<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Spec (b): a Member gets 403 on the members list, register, payments, masters, users,
 * settings and reports. Spec (a): only a user holding the "Manage ..." permissions can
 * add/edit/delete data; everyone else is read-only or refused entirely.
 */
class StaffAreaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function memberRoleUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Member Login');

        return $user;
    }

    public static function staffScreensProvider(): array
    {
        return [
            'members list' => ['members.index'],
            'contributions register' => ['contributions.register'],
            'contributions payments' => ['contributions.payments.index'],
            'masters: loan types' => ['loan-types.index'],
            'masters: contribution settings' => ['contribution-settings.index'],
            'masters: payment methods' => ['payment-methods.index'],
            'masters: expense categories' => ['expense-categories.index'],
            'masters: reminders' => ['reminders.index'],
            'users' => ['users.index'],
            'roles' => ['roles.index'],
            'settings' => ['configurations.index'],
            'reports' => ['reports.index'],
        ];
    }

    #[DataProvider('staffScreensProvider')]
    public function test_b_a_member_is_forbidden_from_staff_screens(string $routeName): void
    {
        $response = $this->actingAs($this->memberRoleUser())->get(route($routeName));

        $response->assertForbidden();
    }

    public function test_a_a_member_cannot_create_a_member_record(): void
    {
        $response = $this->actingAs($this->memberRoleUser())->post(route('members.store'), [
            'name' => 'New Member',
            'phone' => '255712345678',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('members', ['name' => 'New Member']);
    }

    public function test_a_a_user_with_manage_members_can_create_a_member_record(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'Manage Members', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->post(route('members.store'), [
            'name' => 'New Member',
            'phone' => '255712345678',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['name' => 'New Member']);
    }

    public function test_a_a_member_cannot_delete_a_payment(): void
    {
        $member = Member::factory()->create();
        $payment = Payment::create([
            'member_id' => $member->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'payment_type_id' => PaymentType::where('name', 'Monthly Fee')->value('id'),
            'amount' => 1000,
            'paid_on' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->memberRoleUser())->delete(route('contributions.payments.destroy', $payment));

        $response->assertForbidden();
        $this->assertModelExists($payment);
    }
}
