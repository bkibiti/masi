<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Spec section 1: admin can create a login for a member, link an existing user, bulk-create
 * logins for members without one, reset a password, and disable/enable a login.
 */
class MemberUserLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'Manage Members', 'guard_name' => 'web']));

        return $user;
    }

    public function test_admin_can_create_a_login_for_a_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('members.login.create', $member));

        $response->assertRedirect();
        $member->refresh();
        $this->assertNotNull($member->user_id);
        $this->assertTrue($member->user->must_change_password);
        $this->assertTrue($member->user->hasRole('Member Login'));
        $response->assertSessionHas('temp_password');
    }

    public function test_admin_can_link_an_existing_user_to_a_member(): void
    {
        $member = Member::factory()->create();
        $existingUser = User::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('members.login.link', $member), [
            'user_id' => $existingUser->id,
        ]);

        $response->assertRedirect();
        $this->assertSame($existingUser->id, $member->refresh()->user_id);
        $this->assertTrue($existingUser->refresh()->hasRole('Member Login'));
    }

    public function test_admin_cannot_link_a_user_already_linked_to_another_member(): void
    {
        $alreadyLinkedMember = Member::factory()->create();
        $takenUser = User::factory()->create();
        $alreadyLinkedMember->user_id = $takenUser->id;
        $alreadyLinkedMember->save();

        $target = Member::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('members.login.link', $target), [
            'user_id' => $takenUser->id,
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertNull($target->refresh()->user_id);
    }

    public function test_bulk_create_creates_logins_only_for_members_without_one(): void
    {
        $withoutLogin1 = Member::factory()->create();
        $withoutLogin2 = Member::factory()->create();
        $alreadyLinked = Member::factory()->create();
        $alreadyLinked->user_id = User::factory()->create()->id;
        $alreadyLinked->save();

        $response = $this->actingAs($this->admin())->post(route('members.login.bulk-create.store'));

        $response->assertOk();
        $this->assertNotNull($withoutLogin1->refresh()->user_id);
        $this->assertNotNull($withoutLogin2->refresh()->user_id);
        $response->assertViewHas('created', fn ($created) => $created->count() === 2);
    }

    public function test_admin_can_reset_the_password_for_a_members_login(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['must_change_password' => false]);
        $member->user_id = $user->id;
        $member->save();

        $response = $this->actingAs($this->admin())->post(route('members.login.reset-password', $member));

        $response->assertRedirect();
        $response->assertSessionHas('temp_password');
        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_admin_can_disable_and_enable_a_members_login(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $member->user_id = $user->id;
        $member->save();

        $this->actingAs($this->admin())->post(route('members.login.disable', $member))->assertRedirect();
        $this->assertSame(0, $user->refresh()->status);

        $this->actingAs($this->admin())->post(route('members.login.enable', $member))->assertRedirect();
        $this->assertSame(1, $user->refresh()->status);
    }

    public function test_a_user_without_manage_members_cannot_create_a_login(): void
    {
        $member = Member::factory()->create();
        $plainUser = User::factory()->create();

        $response = $this->actingAs($plainUser)->post(route('members.login.create', $member));

        $response->assertForbidden();
        $this->assertNull($member->refresh()->user_id);
    }
}
