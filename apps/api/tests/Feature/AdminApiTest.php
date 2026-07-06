<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_admin_routes(): void
    {
        foreach ([
            ['GET', '/api/v1/admin/summary'],
            ['GET', '/api/v1/admin/users'],
            ['GET', '/api/v1/admin/users/1'],
            ['PATCH', '/api/v1/admin/users/1/access'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }
    }

    public function test_non_admin_users_receive_json_forbidden(): void
    {
        $user = User::factory()->freeUser()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/summary')
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access is required.');
    }

    public function test_admin_can_access_summary_with_correct_platform_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $internal = User::factory()->internalCreator()->create();
        User::factory()->subscriber()->create();
        User::factory()->freeUser()->create();

        $affiliate = Project::factory()->for($admin)->create(['type' => 'affiliate_campaign']);
        $video = Project::factory()->for($internal)->create(['type' => 'video_enhancer']);
        $product = Project::factory()->for($internal)->create(['type' => 'product_to_video']);

        $affiliateGeneration = Generation::factory()->for($admin)->for($affiliate)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);
        $videoGeneration = Generation::factory()->for($internal)->for($video)->create([
            'job_type' => 'generate_video_enhancer',
            'status' => 'failed',
            'credits_charged' => 0,
        ]);
        Generation::factory()->for($internal)->for($product)->create([
            'job_type' => 'generate_product_video_script',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        CreditTransaction::factory()->for($admin)->for($affiliateGeneration)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 2,
            'balance_after' => 18,
        ]);
        CreditTransaction::factory()->for($internal)->for($videoGeneration)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 0,
            'balance_after' => 0,
            'metadata' => ['unlimited_access' => true],
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/summary')
            ->assertOk()
            ->assertJsonPath('total_users', 4)
            ->assertJsonPath('total_admins', 1)
            ->assertJsonPath('total_internal_creators', 1)
            ->assertJsonPath('total_subscribers', 1)
            ->assertJsonPath('total_free_users', 1)
            ->assertJsonPath('total_projects', 3)
            ->assertJsonPath('total_generations', 3)
            ->assertJsonPath('total_completed_generations', 2)
            ->assertJsonPath('total_failed_generations', 1)
            ->assertJsonPath('total_credits_spent', 2)
            ->assertJsonPath('total_unlimited_generations', 1)
            ->assertJsonPath('projects_by_type.affiliate_campaign', 1)
            ->assertJsonPath('projects_by_type.video_enhancer', 1)
            ->assertJsonPath('projects_by_type.product_to_video', 1)
            ->assertJsonPath('generations_by_job_type.generate_affiliate_campaign', 1)
            ->assertJsonPath('generations_by_job_type.generate_video_enhancer', 1)
            ->assertJsonPath('generations_by_job_type.generate_product_video_script', 1);
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Platform Admin']);
        $subscriber = User::factory()->subscriber()->create([
            'name' => 'Creator Subscriber',
            'email' => 'subscriber@example.test',
        ]);
        Project::factory()->for($subscriber)->count(2)->create();
        Generation::factory()->for($subscriber)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/users?search=creator&role=subscriber&plan_key=starter&is_unlimited=0');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $subscriber->id)
            ->assertJsonPath('data.0.projects_count', 2)
            ->assertJsonPath('data.0.generations_count', 1)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'email',
                    'role',
                    'plan_key',
                    'is_unlimited',
                    'monthly_credit_allowance',
                    'credits_balance',
                    'projects_count',
                    'generations_count',
                    'created_at',
                    'updated_at',
                ]],
                'links',
                'meta',
            ]);
    }

    public function test_admin_can_view_user_detail_with_stats_and_recent_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->subscriber()->create();
        $project = Project::factory()->for($user)->create(['title' => 'Managed project']);
        $generation = Generation::factory()->for($user)->for($project)->create([
            'status' => 'completed',
            'job_type' => 'generate_affiliate_campaign',
        ]);
        CreditTransaction::factory()->for($user)->for($generation)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 3,
            'balance_after' => 17,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.stats.projects_count', 1)
            ->assertJsonPath('data.stats.generations_count', 1)
            ->assertJsonPath('data.stats.completed_generations_count', 1)
            ->assertJsonPath('data.stats.failed_generations_count', 0)
            ->assertJsonPath('data.stats.credits_spent', 3)
            ->assertJsonPath('data.recent_projects.0.title', 'Managed project')
            ->assertJsonPath('data.recent_generations.0.id', $generation->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_admin_can_update_access_fields_without_changing_identity_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->freeUser()->create([
            'name' => 'Original Name',
            'email' => 'original@example.test',
            'password' => 'original-password',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/access", [
                'role' => 'internal_creator',
                'plan_key' => 'internal_unlimited',
                'is_unlimited' => true,
                'monthly_credit_allowance' => 100,
                'credits_balance' => 50,
                'name' => 'Changed Name',
                'email' => 'changed@example.test',
                'password' => 'changed-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'internal_creator')
            ->assertJsonPath('data.plan_key', 'internal_unlimited')
            ->assertJsonPath('data.is_unlimited', true)
            ->assertJsonPath('data.monthly_credit_allowance', 100)
            ->assertJsonPath('data.credits_balance', 50);

        $user->refresh();

        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@example.test', $user->email);
        $this->assertTrue(Hash::check('original-password', $user->password));
        $this->assertSame('internal_creator', $user->role);
        $this->assertSame('internal_unlimited', $user->plan_key);
        $this->assertTrue($user->is_unlimited);
        $this->assertSame(100, $user->monthly_credit_allowance);
        $this->assertSame(50, $user->credits_balance);
    }

    public function test_invalid_roles_and_plans_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/access", [
                'role' => 'owner',
                'plan_key' => 'enterprise',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'plan_key']);
    }

    public function test_negative_credit_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/access", [
                'credits_balance' => -1,
                'monthly_credit_allowance' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credits_balance', 'monthly_credit_allowance']);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$admin->id}/access", [
                'role' => 'free_user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role')
            ->assertJsonPath('errors.role.0', 'The last admin account cannot be demoted.');

        $this->assertTrue($admin->refresh()->isAdmin());
    }
}
