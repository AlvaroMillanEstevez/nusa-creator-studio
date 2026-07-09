<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAutoVideoEditJob;
use App\Jobs\GenerateVideoToCampaignJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VideoWorkflowGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_to_campaign_and_auto_editor_tools_appear_in_public_catalog(): void
    {
        $response = $this->getJson('/api/v1/tools')->assertOk();
        $tools = collect($response->json('data'));

        $videoToCampaign = $tools->firstWhere('key', 'video_to_campaign');
        $autoEditor = $tools->firstWhere('key', 'auto_video_editor');

        $this->assertIsArray($videoToCampaign);
        $this->assertSame('available', $videoToCampaign['status']);
        $this->assertSame('advanced', $videoToCampaign['service_tier']);
        $this->assertSame(2, $videoToCampaign['credit_cost']);

        $this->assertIsArray($autoEditor);
        $this->assertSame('available', $autoEditor['status']);
        $this->assertSame('premium', $autoEditor['service_tier']);
        $this->assertSame(2, $autoEditor['credit_cost']);
    }

    public function test_video_to_campaign_generates_mock_campaign_from_owned_source_video(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'starter']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'campaign_source_video');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
                'language' => 'spanish',
                'tone' => 'review',
                'campaign_context' => 'Footage for a social commerce review.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_video_to_campaign')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.input_payload.source_video_asset_id', $sourceVideo->id)
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        Queue::assertPushed(
            GenerateVideoToCampaignJob::class,
            fn (GenerateVideoToCampaignJob $job): bool => $job->generationId === $generationId,
        );

        app()->call([new GenerateVideoToCampaignJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('mock', $generation->provider);
        $this->assertArrayHasKey('video_summary', $generation->output_payload);
        $this->assertArrayHasKey('detected_product_moments', $generation->output_payload);
        $this->assertArrayHasKey('recommended_cuts', $generation->output_payload);
        $this->assertArrayHasKey('hooks', $generation->output_payload);
        $this->assertArrayHasKey('narration_voice_script', $generation->output_payload);
        $this->assertArrayHasKey('edit_plan', $generation->output_payload);
        $this->assertSame($sourceVideo->id, $generation->output_payload['source_video_asset_id']);
        $this->assertStringContainsString('v?deo', $generation->output_payload['video_summary']);

        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();
        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(2, $transaction->amount);
        $this->assertSame(8, $transaction->balance_after);
    }

    public function test_video_to_campaign_rejects_other_user_or_non_video_assets(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'starter']);
        $project = $this->workspaceProject($user);
        $otherUser = User::factory()->create();
        $otherVideo = $this->videoAsset($otherUser, $this->workspaceProject($otherUser), 'campaign_source_video');
        $imageAsset = MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $otherVideo->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_asset_id');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $imageAsset->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(10, $user->refresh()->credits_balance);
    }

    public function test_auto_video_editor_reuses_existing_video_analysis_and_voiceover_without_extra_costs(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'campaign_source_video');
        $analysis = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_video_to_campaign',
            'provider' => 'mock',
            'status' => Generation::STATUS_COMPLETED,
            'credits_charged' => 2,
            'input_payload' => ['source_video_asset_id' => $sourceVideo->id],
            'output_payload' => [
                'video_summary' => 'Reusable video campaign summary.',
                'narration_voice_script' => 'Reusable narration script.',
                'source_video_asset_id' => $sourceVideo->id,
            ],
        ]);
        $voiceover = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_ai_voiceover',
            'provider' => 'mock',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'voiceover_title' => 'Reusable voiceover',
                'narration_script' => 'Voiceover from saved generation.',
            ],
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => true,
                'voiceover_generation_id' => $voiceover->id,
                'editing_style' => 'fast_cuts',
                'target_duration_seconds' => 30,
            ])
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_auto_video_edit')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.input_payload.cost_breakdown.video_analysis_cost', 0)
            ->assertJsonPath('data.input_payload.cost_breakdown.voiceover_cost', 0)
            ->assertJsonPath('data.input_payload.cost_breakdown.total_credits', 2)
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        Queue::assertPushed(
            GenerateAutoVideoEditJob::class,
            fn (GenerateAutoVideoEditJob $job): bool => $job->generationId === $generationId,
        );

        app()->call([new GenerateAutoVideoEditJob($generationId), 'handle']);
        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertArrayHasKey('edit_decision_list', $generation->output_payload);
        $this->assertArrayHasKey('timeline', $generation->output_payload);
        $this->assertArrayHasKey('captions_plan', $generation->output_payload);
        $this->assertArrayHasKey('audio_mix_plan', $generation->output_payload);
        $this->assertArrayHasKey('final_video_placeholder', $generation->output_payload);
        $this->assertSame('Video campaign analysis reused', $generation->output_payload['cost_breakdown']['video_analysis_note']);
        $this->assertSame('Existing voiceover reused', $generation->output_payload['cost_breakdown']['voiceover_note']);
    }

    public function test_auto_video_editor_from_raw_video_can_charge_composite_cost_and_blocks_insufficient_credits(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 4, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'source_video');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => true,
            ])
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 4)
            ->assertJsonPath('required_credits', 5);

        Queue::assertNothingPushed();
        $this->assertSame(4, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_auto_video_editor_raw_video_without_voiceover_generates_plan(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
                'caption_style' => 'bold_keywords',
            ])
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 4)
            ->assertJsonPath('data.input_payload.cost_breakdown.video_analysis_cost', 2)
            ->assertJsonPath('data.input_payload.cost_breakdown.voiceover_cost', 0)
            ->assertJsonPath('data.input_payload.cost_breakdown.total_credits', 4)
            ->assertJsonPath('meta.credits_balance', 6)
            ->json('data.id');

        app()->call([new GenerateAutoVideoEditJob($generationId), 'handle']);

        $this->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.output_payload.final_video_placeholder.status', 'placeholder')
            ->assertJsonPath('data.output_payload.final_video_placeholder.message', 'Mock edit plan only. Real rendering will be connected later.')
            ->assertJsonPath('data.output_payload.cost_breakdown.voiceover_note', 'No voiceover included');
    }

    public function test_project_actions_include_new_video_workflows_with_reusable_hints(): void
    {
        $user = User::factory()->create(['plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $this->videoAsset($user, $project, 'campaign_source_video');
        Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_video_to_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'edit_plan' => ['title' => 'Reusable edit plan'],
                'narration_voice_script' => 'Reusable narration.',
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk();

        $actions = collect($response->json('data'));
        $videoCampaign = $actions->firstWhere('key', 'generate_video_to_campaign');
        $autoEditor = $actions->firstWhere('key', 'generate_auto_video_edit');

        $this->assertIsArray($videoCampaign);
        $this->assertSame('advanced', $videoCampaign['service_tier']);
        $this->assertSame('/api/v1/projects/'.$project->id.'/generate-video-campaign', $videoCampaign['action_endpoint']);
        $this->assertNotEmpty($videoCampaign['recommended_assets']);

        $this->assertIsArray($autoEditor);
        $this->assertSame('premium', $autoEditor['service_tier']);
        $this->assertSame('Can reuse existing video campaign analysis.', $autoEditor['reason']);
        $this->assertNotEmpty($autoEditor['recommended_sources']);
    }

    private function workspaceProject(User $user): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Creator product workspace',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Creator desk light',
                'product_description' => 'A compact light for creator desks.',
                'target_audience' => 'TikTok affiliate creators',
                'language' => 'english',
                'tone' => 'natural',
            ],
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function videoAsset(User $user, Project $project, string $role): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'video',
            'usage_role' => $role,
            'original_name' => 'raw-product-demo.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1_500_000,
        ]);
    }
}
