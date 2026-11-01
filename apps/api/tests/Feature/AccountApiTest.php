<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'creator@example.test',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Sari Creator',
                'email' => $user->email,
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Sari Creator')
            ->assertJsonPath('user.email', $user->email);

        $this->assertSame('Sari Creator', $user->refresh()->name);
    }

    public function test_authenticated_user_can_update_their_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'email' => 'new@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.test');

        $this->assertSame('new@example.test', $user->refresh()->email);
    }

    public function test_profile_email_must_be_unique_except_for_the_current_user(): void
    {
        $user = User::factory()->create(['email' => 'creator@example.test']);
        $other = User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'email' => $other->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('creator@example.test', $user->refresh()->email);
    }

    public function test_authenticated_user_can_update_preferred_locale(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'en',
            'email' => 'creator@example.test',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'preferred_locale' => 'id',
            ])
            ->assertOk()
            ->assertJsonPath('user.preferred_locale', 'id');

        $this->assertSame('id', $user->refresh()->preferred_locale);
    }

    public function test_invalid_preferred_locale_is_rejected(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'en',
            'email' => 'creator@example.test',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'preferred_locale' => 'fr',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preferred_locale');

        $this->assertSame('en', $user->refresh()->preferred_locale);
    }

    public function test_profile_update_cannot_change_access_or_credit_fields(): void
    {
        $user = User::factory()->freeUser()->create([
            'credits_balance' => 12,
            'monthly_credit_allowance' => 20,
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Updated Creator',
                'email' => $user->email,
                'role' => 'admin',
                'plan_key' => 'internal_unlimited',
                'is_unlimited' => true,
                'monthly_credit_allowance' => 9999,
                'credits_balance' => 9999,
                'preferred_locale' => 'es',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertSame('Updated Creator', $user->name);
        $this->assertSame('free_user', $user->role);
        $this->assertSame('free', $user->plan_key);
        $this->assertFalse($user->is_unlimited);
        $this->assertSame(20, $user->monthly_credit_allowance);
        $this->assertSame(12, $user->credits_balance);
        $this->assertSame('es', $user->preferred_locale);

        $response
            ->assertJsonPath('user.role', 'free_user')
            ->assertJsonPath('user.plan_key', 'free')
            ->assertJsonPath('user.is_unlimited', false)
            ->assertJsonPath('user.monthly_credit_allowance', 20)
            ->assertJsonPath('user.credits_balance', 12)
            ->assertJsonPath('user.preferred_locale', 'es');
    }

    public function test_authenticated_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'old-password',
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $this->assertTrue(Hash::check('new-strong-password', $user->refresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function test_password_update_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'incorrect-password',
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    public function test_account_update_endpoints_require_authentication(): void
    {
        $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Guest',
            'email' => 'guest@example.test',
        ])->assertUnauthorized();

        $this->patchJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertUnauthorized();
    }
}
