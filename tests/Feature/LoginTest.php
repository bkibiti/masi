<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec (f): login works with email and with a mobile in any of the three formats; a
 * temporary password forces a change; a disabled login can't sign in.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_f_login_with_email(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['username' => $user->email, 'password' => 'secret123']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public static function mobileFormatProvider(): array
    {
        return [
            'leading zero' => ['0712345678'],
            'no prefix' => ['712345678'],
            '255 prefix' => ['255712345678'],
            '+255 prefix' => ['+255712345678'],
        ];
    }

    #[DataProvider('mobileFormatProvider')]
    public function test_f_login_with_mobile_in_any_format(string $enteredPhone): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $member = Member::factory()->create(['phone' => '255712345678']);
        $member->user_id = $user->id;
        $member->save();

        $response = $this->post('/login', ['username' => $enteredPhone, 'password' => 'secret123']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_f_wrong_password_on_mobile_login_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $member = Member::factory()->create(['phone' => '255712345678']);
        $member->user_id = $user->id;
        $member->save();

        $response = $this->post('/login', ['username' => '0712345678', 'password' => 'wrong']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_f_a_temporary_password_forces_a_change_before_anything_else(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($user)->get(route('home'));
        $response->assertRedirect(route('account.force-password'));

        $update = $this->actingAs($user)->post(route('account.force-password.update'), [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);
        $update->assertRedirect(route('home'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_f_a_disabled_login_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 0, 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['username' => $user->email, 'password' => 'secret123']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
