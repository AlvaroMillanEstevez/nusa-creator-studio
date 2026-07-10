<?php

namespace Tests\Feature;

use App\Models\CreatorTool;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CreatorToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorToolApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_tool_seeder_is_idempotent(): void
    {
        $this->seed(CreatorToolSeeder::class);
        $this->seed(CreatorToolSeeder::class);

        $this->assertDatabaseCount('creator_tools', 11);
    }

    public function test_seeded_existing_tools_have_service_tier_output_type_and_stage(): void
    {
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'affiliate_campaign',
            'service_tier' => 'basic',
            'output_type' => 'script_pack',
            'implementation_stage' => 'mock',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'video_enhancer',
            'service_tier' => 'advanced',
            'output_type' => 'assisted_media',
            'implementation_stage' => 'mock',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'product_to_video',
            'service_tier' => 'advanced',
            'output_type' => 'assisted_media',
            'implementation_stage' => 'mock',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'ai_voiceover',
            'service_tier' => 'advanced',
            'output_type' => 'audio',
            'implementation_stage' => 'real_provider',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'video_to_campaign',
            'service_tier' => 'advanced',
            'output_type' => 'assisted_media',
            'implementation_stage' => 'beta',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'fashion_try_on',
            'service_tier' => 'premium',
            'output_type' => 'image',
            'implementation_stage' => 'future',
            'status' => 'coming_soon',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'auto_video_editor',
            'service_tier' => 'premium',
            'output_type' => 'video',
            'implementation_stage' => 'beta',
        ]);
        $this->assertDatabaseHas('creator_tools', [
            'key' => 'motion_ai_video',
            'service_tier' => 'premium',
            'output_type' => 'video',
            'implementation_stage' => 'future',
            'status' => 'coming_soon',
        ]);
    }

    public function test_future_avatar_tools_are_seeded_as_premium_future_tools(): void
    {
        foreach (['ai_avatar_presenter', 'custom_ai_avatar', 'avatar_voice_ads'] as $key) {
            $this->assertDatabaseHas('creator_tools', [
                'key' => $key,
                'category' => 'avatar',
                'status' => 'coming_soon',
                'service_tier' => 'premium',
                'implementation_stage' => 'future',
                'is_visible' => true,
            ]);
        }

        $this->assertDatabaseHas('creator_tools', [
            'key' => 'avatar_voice_ads',
            'output_type' => 'mixed_media',
        ]);
    }

    public function test_public_tools_are_visible_ordered_and_exclude_disabled_tools(): void
    {
        CreatorTool::query()->where('key', 'video_enhancer')->update(['sort_order' => 5]);
        CreatorTool::query()->where('key', 'ai_voiceover')->update([
            'status' => 'maintenance',
            'sort_order' => 8,
        ]);
        CreatorTool::query()->where('key', 'affiliate_campaign')->update(['sort_order' => 10]);
        CreatorTool::query()->where('key', 'product_to_video')->update(['is_visible' => false]);
        CreatorTool::query()->where('key', 'fashion_try_on')->update(['status' => 'disabled']);

        $response = $this->getJson('/api/v1/tools');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.key', 'video_enhancer')
            ->assertJsonPath('data.1.key', 'ai_voiceover')
            ->assertJsonPath('data.1.status', 'maintenance')
            ->assertJsonPath('data.1.service_tier', 'advanced')
            ->assertJsonPath('data.1.output_type', 'audio')
            ->assertJsonPath('data.1.implementation_stage', 'real_provider')
            ->assertJsonPath('data.2.key', 'affiliate_campaign')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.metadata');

        $keys = collect($response->json('data'))->pluck('key');

        $this->assertFalse($keys->contains('product_to_video'));
        $this->assertFalse($keys->contains('fashion_try_on'));
    }

    public function test_admin_can_list_all_tools_including_disabled_and_invisible(): void
    {
        $admin = User::factory()->admin()->create();

        CreatorTool::query()->where('key', 'ai_voiceover')->update([
            'status' => 'disabled',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/tools');

        $response
            ->assertOk()
            ->assertJsonCount(11, 'data');

        $tool = collect($response->json('data'))->firstWhere('key', 'ai_voiceover');

        $this->assertSame('disabled', $tool['status']);
        $this->assertFalse($tool['is_visible']);
    }

    public function test_admin_can_update_tool_management_fields_without_changing_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/tools/affiliate_campaign', [
                'key' => 'changed_key',
                'status' => 'maintenance',
                'service_tier' => 'premium',
                'output_type' => 'mixed_media',
                'implementation_stage' => 'beta',
                'credit_cost' => 3,
                'sort_order' => 2,
                'is_recommended' => false,
                'is_visible' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'affiliate_campaign')
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.service_tier', 'premium')
            ->assertJsonPath('data.output_type', 'mixed_media')
            ->assertJsonPath('data.implementation_stage', 'beta')
            ->assertJsonPath('data.credit_cost', 3)
            ->assertJsonPath('data.sort_order', 2);

        $this->assertDatabaseHas('creator_tools', [
            'key' => 'affiliate_campaign',
            'status' => 'maintenance',
            'service_tier' => 'premium',
            'output_type' => 'mixed_media',
            'implementation_stage' => 'beta',
            'credit_cost' => 3,
            'sort_order' => 2,
        ]);
        $this->assertDatabaseMissing('creator_tools', ['key' => 'changed_key']);
    }

    public function test_invalid_service_tier_output_type_and_implementation_stage_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/tools/affiliate_campaign', [
                'service_tier' => 'enterprise',
                'output_type' => 'spreadsheet',
                'implementation_stage' => 'secret_provider',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_tier', 'output_type', 'implementation_stage']);
    }

    public function test_implemented_tool_route_must_match_its_known_frontend_route_or_be_null(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/tools/video_enhancer', [
                'route' => '/campaigns/new',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('route');

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/tools/video_enhancer', [
                'route' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.route', null);
    }

    public function test_unauthenticated_user_cannot_access_admin_tool_endpoints(): void
    {
        foreach ([
            ['GET', '/api/v1/admin/tools'],
            ['GET', '/api/v1/admin/tools/affiliate_campaign'],
            ['PATCH', '/api/v1/admin/tools/affiliate_campaign'],
        ] as [$method, $uri]) {
            $this->json($method, $uri, ['status' => 'maintenance'])
                ->assertUnauthorized();
        }
    }

    public function test_non_admin_cannot_access_admin_tool_endpoints(): void
    {
        $user = User::factory()->freeUser()->create();

        foreach ([
            ['GET', '/api/v1/admin/tools'],
            ['GET', '/api/v1/admin/tools/affiliate_campaign'],
            ['PATCH', '/api/v1/admin/tools/affiliate_campaign'],
        ] as [$method, $uri]) {
            $this->actingAs($user)
                ->json($method, $uri, ['status' => 'maintenance'])
                ->assertForbidden()
                ->assertJsonPath('message', 'Admin access is required.');
        }
    }

    public function test_project_creation_is_blocked_when_its_tool_is_not_available(): void
    {
        $user = User::factory()->create();

        CreatorTool::query()->where('key', 'affiliate_campaign')->update([
            'status' => 'maintenance',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/projects', [
                'title' => 'Blocked campaign',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'tool_unavailable')
            ->assertJsonPath('message', 'This tool is currently unavailable.');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_each_generation_endpoint_is_blocked_when_its_tool_is_not_available(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);

        foreach ([
            ['affiliate_campaign', 'affiliate_campaign', 'generate-campaign'],
            ['video_enhancer', 'video_enhancer', 'generate-video-enhancer'],
            ['product_to_video', 'product_to_video', 'generate-product-video-script'],
        ] as [$type, $key, $action]) {
            $project = Project::factory()->for($user)->create(['type' => $type]);
            CreatorTool::query()->where('key', $key)->update(['status' => 'maintenance']);

            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/{$action}")
                ->assertUnprocessable()
                ->assertJsonPath('code', 'tool_unavailable')
                ->assertJsonPath('message', 'This tool is temporarily unavailable.');

            CreatorTool::query()->where('key', $key)->update(['status' => 'available']);
        }

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $user->refresh()->credits_balance);
    }

    public function test_existing_project_and_history_remain_readable_during_maintenance(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'type' => 'affiliate_campaign',
            'title' => 'Existing campaign',
        ]);
        $generation = Generation::factory()->for($user)->for($project)->create();

        CreatorTool::query()->where('key', 'affiliate_campaign')->update([
            'status' => 'maintenance',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Existing campaign');

        $this->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generation->id);
    }
}
