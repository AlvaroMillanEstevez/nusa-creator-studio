<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProjectActionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_actions_include_tier_credit_access_cta_and_recommended_sources(): void
    {
        $user = User::factory()->subscriber()->create(['plan_key' => 'creator']);
        $project = $this->campaignProject($user);
        $olderSource = $this->campaignSourceGeneration($user, $project, [
            'voiceover_ready_script' => 'Older campaign script for voiceover.',
            'campaign_title' => 'Older campaign angle',
        ], now()->subMinutes(5));
        $newestSource = $this->campaignSourceGeneration($user, $project, [
            'voiceover_ready_script' => 'Use this latest campaign script for voiceover.',
            'campaign_title' => 'Latest campaign angle',
        ], now()->subMinute());

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->assertJsonPath('data.0.key', 'generate_campaign')
            ->assertJsonPath('data.0.status', 'available');

        $actions = collect($response->json('data'));
        $campaignAction = $this->action($actions, 'generate_campaign');
        $voiceoverAction = $this->action($actions, 'generate_voiceover_from_script');

        $this->assertSame('basic', $campaignAction['service_tier']);
        $this->assertSame('script_pack', $campaignAction['output_type']);
        $this->assertSame(1, $campaignAction['credit_cost']);
        $this->assertTrue($campaignAction['user_has_access']);
        $this->assertFalse($campaignAction['upgrade_required']);
        $this->assertSame('direct', $campaignAction['action_mode']);
        $this->assertSame('Generate another campaign version', $campaignAction['primary_cta']['label']);
        $this->assertSame('generate_campaign', $campaignAction['primary_cta']['action']);
        $this->assertSame(0, $campaignAction['missing_requirements_count']);

        $this->assertSame('advanced', $voiceoverAction['service_tier']);
        $this->assertSame('audio', $voiceoverAction['output_type']);
        $this->assertSame(1, $voiceoverAction['credit_cost']);
        $this->assertSame('available', $voiceoverAction['status']);
        $this->assertSame('direct', $voiceoverAction['action_mode']);
        $this->assertTrue($voiceoverAction['user_has_access']);
        $this->assertFalse($voiceoverAction['upgrade_required']);
        $this->assertSame('source_generation', $voiceoverAction['required_source']);
        $this->assertSame($newestSource->id, $voiceoverAction['source_generation_id']);
        $this->assertSame('/api/v1/projects/'.$project->id.'/generate-voiceover', $voiceoverAction['action_endpoint']);
        $this->assertSame($newestSource->id, $voiceoverAction['recommended_sources'][0]['generation_id']);
        $this->assertSame('Latest campaign angle', $voiceoverAction['recommended_sources'][0]['title']);
        $this->assertSame($olderSource->id, $voiceoverAction['recommended_sources'][1]['generation_id']);
    }

    public function test_free_user_sees_basic_action_available_and_advanced_premium_actions_locked_for_upgrade(): void
    {
        $user = User::factory()->freeUser()->create();
        $project = $this->campaignProject($user);
        $this->campaignSourceGeneration($user, $project);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));

        $campaignAction = $this->action($actions, 'generate_campaign');
        $voiceoverAction = $this->action($actions, 'generate_voiceover_from_script');
        $fashionAction = $this->action($actions, 'start_fashion_try_on');

        $this->assertSame('available', $campaignAction['status']);
        $this->assertTrue($campaignAction['user_has_access']);
        $this->assertFalse($campaignAction['upgrade_required']);

        $this->assertSame('unavailable', $voiceoverAction['status']);
        $this->assertSame('upgrade', $voiceoverAction['action_mode']);
        $this->assertFalse($voiceoverAction['user_has_access']);
        $this->assertTrue($voiceoverAction['upgrade_required']);
        $this->assertSame('Upgrade subscription', $voiceoverAction['primary_cta']['label']);
        $this->assertSame('/pricing', $voiceoverAction['primary_cta']['route']);
        $this->assertNotNull($voiceoverAction['locked_reason']);

        $this->assertSame('coming_soon', $fashionAction['status']);
        $this->assertSame('coming_soon', $fashionAction['action_mode']);
        $this->assertFalse($fashionAction['user_has_access']);
        $this->assertFalse($fashionAction['upgrade_required']);
        $this->assertSame('Coming soon', $fashionAction['primary_cta']['label']);
        $this->assertNull($fashionAction['primary_cta']['route']);
    }

    public function test_internal_unlimited_user_has_access_to_all_implemented_actions(): void
    {
        $user = User::factory()->internalCreator()->create();
        $project = $this->campaignProject($user);
        $this->campaignSourceGeneration($user, $project);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));

        foreach (['generate_campaign', 'generate_voiceover_from_script', 'generate_video_to_campaign', 'generate_auto_video_edit', 'start_motion_video'] as $key) {
            $action = $this->action($actions, $key);

            $this->assertTrue($action['user_has_access'], "Expected {$key} to be accessible.");
            $this->assertFalse($action['upgrade_required'], "Expected {$key} not to require an upgrade.");
            $this->assertSame('Internal/unlimited access', $action['access_label']);
        }

        foreach (['start_fashion_try_on'] as $key) {
            $action = $this->action($actions, $key);

            $this->assertSame('coming_soon', $action['status']);
            $this->assertSame('coming_soon', $action['action_mode']);
            $this->assertFalse($action['upgrade_required']);
        }
    }

    public function test_campaign_action_remains_available_after_previous_campaign_generation(): void
    {
        $user = User::factory()->create();
        $project = $this->campaignProject($user);
        $this->campaignSourceGeneration($user, $project);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));
        $campaignAction = $this->action($actions, 'generate_campaign');

        $this->assertSame('available', $campaignAction['status']);
        $this->assertSame('direct', $campaignAction['action_mode']);
        $this->assertSame('Generate another campaign version', $campaignAction['primary_cta']['label']);
    }

    public function test_voiceover_action_remains_available_after_previous_voiceover_when_source_exists(): void
    {
        $user = User::factory()->subscriber()->create(['plan_key' => 'starter']);
        $project = $this->campaignProject($user);
        $sourceGeneration = $this->campaignSourceGeneration($user, $project);
        Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'job_type' => 'generate_ai_voiceover',
                'status' => Generation::STATUS_COMPLETED,
                'input_payload' => [
                    'source_generation_id' => $sourceGeneration->id,
                    'source_text' => 'Previous voiceover text.',
                ],
            ]);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));
        $voiceoverAction = $this->action($actions, 'generate_voiceover_from_script');

        $this->assertSame('available', $voiceoverAction['status']);
        $this->assertSame('direct', $voiceoverAction['action_mode']);
        $this->assertSame('Create another voiceover', $voiceoverAction['primary_cta']['label']);
        $this->assertSame($sourceGeneration->id, $voiceoverAction['source_generation_id']);
        $this->assertSame($sourceGeneration->id, $voiceoverAction['recommended_sources'][0]['generation_id']);
    }

    public function test_fashion_is_coming_soon_and_motion_is_configurable_with_requirements(): void
    {
        $user = User::factory()->subscriber()->create(['plan_key' => 'creator']);
        $project = $this->campaignProject($user);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));
        $fashionAction = $this->action($actions, 'start_fashion_try_on');
        $motionAction = $this->action($actions, 'start_motion_video');

        $this->assertSame('coming_soon', $fashionAction['status']);
        $this->assertSame('coming_soon', $fashionAction['action_mode']);
        $this->assertSame('Coming soon', $fashionAction['primary_cta']['label']);
        $this->assertSame(['model_reference', 'garment_reference'], array_column($fashionAction['requirements'], 'key'));
        $this->assertSame(2, $fashionAction['missing_requirements_count']);

        $this->assertSame('configure', $motionAction['status']);
        $this->assertSame('configure', $motionAction['action_mode']);
        $this->assertSame('Configure Motion AI Video', $motionAction['primary_cta']['label']);
        $this->assertSame("/projects/{$project->id}/actions/motion-ai-video", $motionAction['primary_cta']['route']);
        $this->assertSame('/api/v1/projects/'.$project->id.'/generate-motion-ai-video', $motionAction['action_endpoint']);
        $this->assertSame(
            ['product_image', 'scenario_image', 'campaign_script'],
            array_column($motionAction['requirements'], 'key'),
        );
        $this->assertSame(1, $motionAction['missing_requirements_count']);
    }

    public function test_project_actions_include_recommended_assets_without_requiring_card_level_blockers(): void
    {
        $user = User::factory()->subscriber()->create(['plan_key' => 'creator']);
        $project = $this->campaignProject($user);
        $modelAsset = MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'model_reference',
            'original_name' => 'creator-model.jpg',
        ]);
        $garmentAsset = MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'garment_reference',
            'original_name' => 'linen-shirt.jpg',
        ]);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));
        $fashionAction = $this->action($actions, 'start_fashion_try_on');

        $this->assertSame('coming_soon', $fashionAction['status']);
        $this->assertSame('coming_soon', $fashionAction['action_mode']);
        $this->assertSame(0, $fashionAction['missing_requirements_count']);
        $this->assertSame(
            [$modelAsset->id, $garmentAsset->id],
            array_column($fashionAction['recommended_assets'], 'media_asset_id'),
        );
    }

    public function test_workspace_project_returns_sensible_project_actions(): void
    {
        $user = User::factory()->subscriber()->create(['plan_key' => 'creator']);
        $project = Project::factory()->for($user)->create([
            'title' => 'Creator desk launch workspace',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Creator desk light',
                'content_idea' => 'Compact creator desk lighting kit',
                'product_description' => 'A compact desk light for mobile-first creators.',
                'target_audience' => 'TikTok affiliate creators',
                'language' => 'english',
                'tone' => 'natural',
                'platform' => 'tiktok',
            ],
            'settings' => [
                'platform' => 'tiktok',
                'format' => 'vertical_9_16',
            ],
        ]);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));

        $campaignAction = $this->action($actions, 'generate_campaign');
        $voiceoverAction = $this->action($actions, 'generate_voiceover_from_script');
        $fashionAction = $this->action($actions, 'start_fashion_try_on');
        $motionAction = $this->action($actions, 'start_motion_video');

        $this->assertSame('available', $campaignAction['status']);
        $this->assertSame('Generate campaign', $campaignAction['primary_cta']['label']);
        $this->assertSame(0, $campaignAction['missing_requirements_count']);
        $this->assertSame('configure', $voiceoverAction['status']);
        $this->assertSame('Coming soon', $fashionAction['primary_cta']['label']);
        $this->assertSame('Configure Motion AI Video', $motionAction['primary_cta']['label']);
    }

    public function test_project_actions_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->campaignProject($owner);

        $this->actingAs($other)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertNotFound();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    private function action(Collection $actions, string $key): array
    {
        $action = $actions->firstWhere('key', $key);

        $this->assertIsArray($action, "Expected project action [{$key}] to exist.");

        return $action;
    }

    /** @param array<string, mixed> $outputPayload */
    private function campaignSourceGeneration(
        User $user,
        Project $project,
        array $outputPayload = [],
        ?\DateTimeInterface $createdAt = null,
    ): Generation {
        return Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'job_type' => 'generate_affiliate_campaign',
                'status' => Generation::STATUS_COMPLETED,
                'output_payload' => array_merge([
                    'voiceover_ready_script' => 'Use this campaign script for voiceover.',
                    'campaign_title' => 'Creator light launch angle',
                ], $outputPayload),
                'created_at' => $createdAt ?? now(),
                'updated_at' => $createdAt ?? now(),
            ]);
    }

    private function campaignProject(User $user): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Creator light campaign',
            'type' => 'affiliate_campaign',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Creator light',
                'product_description' => 'A compact light for product videos.',
                'target_audience' => 'Mobile creators',
                'language' => 'english',
                'tone' => 'natural',
            ],
            'settings' => [
                'platform' => 'tiktok',
                'format' => 'vertical_9_16',
            ],
        ]);
    }
}
