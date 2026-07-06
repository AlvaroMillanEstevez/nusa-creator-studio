<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_plan_seeder_is_idempotent(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertDatabaseCount('subscription_plans', 5);
    }

    public function test_public_plans_endpoint_returns_only_active_public_non_internal_plans(): void
    {
        SubscriptionPlan::query()->where('key', 'starter')->update(['is_active' => false]);
        SubscriptionPlan::query()->where('key', 'pro')->update(['is_public' => false]);

        $response = $this->getJson('/api/v1/plans');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.key', 'free')
            ->assertJsonPath('data.1.key', 'creator')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.metadata');

        $keys = collect($response->json('data'))->pluck('key');

        $this->assertFalse($keys->contains('starter'));
        $this->assertFalse($keys->contains('pro'));
        $this->assertFalse($keys->contains('internal_unlimited'));
    }

    public function test_admin_can_list_all_plans_including_internal_and_inactive(): void
    {
        $admin = User::factory()->admin()->create();
        SubscriptionPlan::query()->where('key', 'starter')->update(['is_active' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/plans');

        $response->assertOk()->assertJsonCount(5, 'data');

        $internal = collect($response->json('data'))->firstWhere('key', 'internal_unlimited');
        $starter = collect($response->json('data'))->firstWhere('key', 'starter');

        $this->assertTrue($internal['is_internal']);
        $this->assertFalse($internal['is_public']);
        $this->assertFalse($starter['is_active']);
    }

    public function test_admin_can_update_plan_price_credits_and_features_without_changing_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/plans/creator', [
                'key' => 'renamed',
                'price_amount' => 249000,
                'monthly_credits' => 450,
                'features' => [
                    '450 monthly credits',
                    'More campaign variations',
                ],
                'sort_order' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'creator')
            ->assertJsonPath('data.price_amount', '249000.00')
            ->assertJsonPath('data.monthly_credits', 450)
            ->assertJsonPath('data.features.0', '450 monthly credits')
            ->assertJsonPath('data.sort_order', 25);

        $this->assertDatabaseMissing('subscription_plans', ['key' => 'renamed']);
    }

    public function test_plan_update_validation_rejects_invalid_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/plans/starter', [
                'price_amount' => -1,
                'monthly_credits' => -1,
                'max_projects_per_month' => -1,
                'billing_interval' => 'weekly',
                'currency' => 'RUPIAH',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'price_amount',
                'monthly_credits',
                'max_projects_per_month',
                'billing_interval',
                'currency',
            ]);
    }

    public function test_non_admin_cannot_access_admin_plan_endpoints(): void
    {
        $user = User::factory()->freeUser()->create();

        foreach ([
            ['GET', '/api/v1/admin/plans'],
            ['GET', '/api/v1/admin/plans/free'],
            ['PATCH', '/api/v1/admin/plans/free'],
        ] as [$method, $uri]) {
            $this->actingAs($user)
                ->json($method, $uri, ['monthly_credits' => 99])
                ->assertForbidden();
        }
    }

    public function test_auth_me_does_not_expose_a_private_non_internal_plan(): void
    {
        $user = User::factory()->freeUser()->create();
        SubscriptionPlan::query()->where('key', 'free')->update(['is_public' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.plan_key', 'free')
            ->assertJsonPath('user.current_plan', null);
    }

    public function test_auth_me_includes_the_users_current_plan_even_when_it_is_internal(): void
    {
        $user = User::factory()->internalCreator()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.plan_key', 'internal_unlimited')
            ->assertJsonPath('user.current_plan.key', 'internal_unlimited')
            ->assertJsonPath('user.current_plan.name', 'Internal Unlimited')
            ->assertJsonPath('user.current_plan.monthly_credits', 0)
            ->assertJsonPath('user.current_plan.billing_interval', 'internal')
            ->assertJsonPath('user.is_unlimited', true);
    }

    public function test_changing_user_plan_syncs_allowance_without_overwriting_credit_balance(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->freeUser()->create([
            'credits_balance' => 7,
            'monthly_credit_allowance' => 20,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/access", [
                'plan_key' => 'creator',
            ])
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'creator')
            ->assertJsonPath('data.monthly_credit_allowance', 300)
            ->assertJsonPath('data.credits_balance', 7);

        $this->assertSame(300, $user->refresh()->monthly_credit_allowance);
        $this->assertSame(7, $user->credits_balance);
    }
}
