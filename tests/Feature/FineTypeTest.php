<?php

namespace Tests\Feature;

use App\Models\FineType;
use App\Models\Member;
use App\Models\MemberFine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('Manage Fine Types');
    }

    public function test_g_a_fine_type_used_on_a_fine_cannot_be_deleted(): void
    {
        $type = FineType::where('name', 'Meeting absence')->first();
        $member = Member::factory()->create();
        MemberFine::create([
            'member_id' => $member->id,
            'fine_type_id' => $type->id,
            'fined_on' => now()->toDateString(),
            'amount' => 5000,
        ]);

        $this->actingAs($this->user)->delete(route('fine-types.destroy', $type));

        $this->assertNotNull($type->fresh());
    }

    public function test_an_unused_fine_type_can_be_deleted(): void
    {
        $type = FineType::create(['name' => 'Unused Type', 'default_amount' => 0, 'is_active' => true]);

        $this->actingAs($this->user)->delete(route('fine-types.destroy', $type));

        $this->assertNull($type->fresh());
    }

    public function test_store_creates_a_fine_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('fine-types.store'), [
            'name' => 'Damaged Property',
            'default_amount' => 10000,
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fine_types', ['name' => 'Damaged Property', 'default_amount' => 10000]);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('fine-types.store'), [
            'name' => 'Other',
            'default_amount' => 1000,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_the_fine_types_index_renders(): void
    {
        $this->user->givePermissionTo('View Fine Types');

        $this->actingAs($this->user)->get(route('fine-types.index'))->assertOk();
    }

    public function test_a_user_without_permission_cannot_manage_fine_types(): void
    {
        $other = User::factory()->create();
        $type = FineType::create(['name' => 'Guest Type', 'default_amount' => 0, 'is_active' => true]);

        $this->actingAs($other)->post(route('fine-types.store'), ['name' => 'X', 'default_amount' => 0])->assertForbidden();
        $this->actingAs($other)->put(route('fine-types.update', $type), ['name' => 'X', 'default_amount' => 0])->assertForbidden();
        $this->actingAs($other)->delete(route('fine-types.destroy', $type))->assertForbidden();
    }
}
