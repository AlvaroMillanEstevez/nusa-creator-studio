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
        $this->assertSame('video_analysis', $generation->output_payload['campaign_input_mode']);
        $this->assertTrue($generation->output_payload['has_source_video']);
        $this->assertTrue($generation->output_payload['has_edit_plan']);
        $this->assertTrue($generation->output_payload['auto_video_editor_eligible']);
        $this->assertStringContainsString('vídeo', $generation->output_payload['video_summary']);

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
                'recommended_cuts' => [[
                    'start_seconds' => 0,
                    'end_seconds' => 3,
                    'reason' => 'Open on product.',
                ]],
                'edit_plan' => ['timeline' => [[
                    'start_seconds' => 0,
                    'end_seconds' => 3,
                    'instruction' => 'Open on product.',
                ]]],
                'campaign_input_mode' => 'video_analysis',
                'has_source_video' => true,
                'has_edit_plan' => true,
                'auto_video_editor_eligible' => true,
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
                'edit_mode' => 'ai_video_analysis',
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
            ->assertJsonPath('data.input_payload.edit_mode', 'ai_video_analysis')
            ->assertJsonPath('data.input_payload.ai_video_analysis_used', true)
            ->assertJsonPath('data.input_payload.fallback_timeline', false)
            ->assertJsonPath('data.input_payload.source_audio_mode', 'mute_source')
            ->assertJsonPath('data.input_payload.voiceover_mix', 'voiceover_dominant')
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

    public function test_auto_video_editor_manual_edit_blocks_insufficient_credits(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'source_video');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => true,
            ])
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 2)
            ->assertJsonPath('required_credits', 3);

        Queue::assertNothingPushed();
        $this->assertSame(2, $user->refresh()->credits_balance);
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
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
                'caption_style' => 'bold_keywords',
            ])
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.input_payload.edit_mode', 'manual_edit')
            ->assertJsonPath('data.input_payload.fallback_timeline', true)
            ->assertJsonPath('data.input_payload.ai_video_analysis_used', false)
            ->assertJsonPath('data.input_payload.cost_breakdown.video_analysis_cost', 0)
            ->assertJsonPath('data.input_payload.cost_breakdown.voiceover_cost', 0)
            ->assertJsonPath('data.input_payload.cost_breakdown.total_credits', 2)
            ->assertJsonPath('data.input_payload.source_audio_mode', 'low_source')
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        app()->call([new GenerateAutoVideoEditJob($generationId), 'handle']);

        $this->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.output_payload.final_video_placeholder.status', 'placeholder')
            ->assertJsonPath('data.output_payload.final_video_placeholder.message', 'Mock edit plan only. Real rendering will be connected later.')
            ->assertJsonPath('data.output_payload.cost_breakdown.voiceover_note', 'No voiceover included');
    }

    public function test_auto_video_editor_rejects_non_video_campaign_generation_in_ai_analysis_mode(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $this->videoAsset($user, $project, 'source_video');
        $textCampaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'provider' => 'mock',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'campaign_title' => 'Brief-only kit',
                'campaign_input_mode' => 'brief_only',
                'auto_video_editor_eligible' => false,
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'ai_video_analysis',
                'source_video_to_campaign_generation_id' => $textCampaign->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_to_campaign_generation_id')
            ->assertJsonFragment([
                'Selected campaign does not include video analysis data. Choose a Video-to-Campaign Insight or use Manual edit setup.',
            ]);

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', [
            'job_type' => 'generate_auto_video_edit',
        ]);
    }

    public function test_auto_video_editor_rejects_manual_edit_without_source_video(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'include_voiceover' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', [
            'job_type' => 'generate_auto_video_edit',
        ]);
    }

    public function test_auto_video_editor_accepts_and_normalizes_caption_styles(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        $cases = [
            'clean_subtitles' => 'clean_subtitles',
            'bold_hook' => 'bold_hook',
            'minimal' => 'minimal',
            'premium_clean' => 'premium_clean',
            'ugc_native' => 'ugc_native',
            'missing' => 'clean_subtitles',
            'clean' => 'clean_subtitles',
            'subtitles' => 'clean_subtitles',
            'bold' => 'bold_hook',
            'premium' => 'premium_clean',
            'ugc' => 'ugc_native',
            'native' => 'ugc_native',
            'bold_keywords' => 'bold_hook',
            'karaoke' => 'ugc_native',
        ];

        foreach ($cases as $inputStyle => $expectedStyle) {
            $payload = [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
            ];

            if ($inputStyle !== 'missing') {
                $payload['caption_style'] = $inputStyle;
            }

            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", $payload)
                ->assertCreated()
                ->assertJsonPath('data.input_payload.caption_style', $expectedStyle)
                ->assertJsonPath('data.input_payload.creative_brief.caption_style', $expectedStyle);
        }
    }

    public function test_auto_video_editor_rejects_invalid_caption_style(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
                'caption_style' => 'chaotic_glitter_captions',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('caption_style');
    }

    public function test_auto_video_editor_accepts_all_source_audio_modes(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        foreach (['mute_source', 'low_source', 'keep_source', 'product_sounds'] as $mode) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                    'source_video_asset_id' => $sourceVideo->id,
                    'include_voiceover' => false,
                    'source_audio_mode' => $mode,
                ])
                ->assertCreated()
                ->assertJsonPath('data.input_payload.source_audio_mode', $mode)
                ->assertJsonPath('data.input_payload.creative_brief.source_audio_mode', $mode);
        }
    }

    public function test_auto_video_editor_accepts_audio_cleanup_and_edit_polish_settings(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
                'source_audio_mode' => 'product_sounds',
                'voiceover_mix' => 'balanced',
                'transition_style' => 'snappy_social',
                'motion_polish' => 'product_focus',
                'clip_diversity' => 'more_unique_shots',
                'end_screen' => 'simple_cta',
                'overlay_style' => 'product_highlights',
                'cta' => 'Check the product details.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.input_payload.source_audio_mode', 'product_sounds')
            ->assertJsonPath('data.input_payload.voiceover_mix', 'balanced')
            ->assertJsonPath('data.input_payload.transition_style', 'snappy_social')
            ->assertJsonPath('data.input_payload.motion_polish', 'product_focus')
            ->assertJsonPath('data.input_payload.clip_diversity', 'more_unique_shots')
            ->assertJsonPath('data.input_payload.end_screen', 'simple_cta')
            ->assertJsonPath('data.input_payload.overlay_style', 'product_highlights')
            ->assertJsonPath('data.input_payload.creative_brief.source_audio_mode', 'product_sounds')
            ->assertJsonPath('data.input_payload.creative_brief.transition_style', 'snappy_social')
            ->assertJsonPath('data.input_payload.creative_brief.motion_polish', 'product_focus')
            ->assertJsonPath('data.input_payload.creative_brief.clip_diversity', 'more_unique_shots')
            ->assertJsonPath('data.input_payload.creative_brief.end_screen', 'simple_cta')
            ->assertJsonPath('data.input_payload.creative_brief.overlay_style', 'product_highlights');
    }

    public function test_auto_video_editor_accepts_all_overlay_styles(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        foreach (['minimal_clean', 'ugc_native', 'product_highlights', 'promo_badges', 'premium_brand'] as $style) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                    'edit_mode' => 'manual_edit',
                    'source_video_asset_id' => $sourceVideo->id,
                    'include_voiceover' => false,
                    'overlay_style' => $style,
                ])
                ->assertCreated()
                ->assertJsonPath('data.input_payload.overlay_style', $style)
                ->assertJsonPath('data.input_payload.creative_brief.overlay_style', $style);
        }
    }

    public function test_auto_video_editor_defaults_overlay_style_by_edit_mode(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');
        $analysis = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_video_to_campaign',
            'provider' => 'mock',
            'status' => Generation::STATUS_COMPLETED,
            'input_payload' => ['source_video_asset_id' => $sourceVideo->id],
            'output_payload' => [
                'source_video_asset_id' => $sourceVideo->id,
                'detected_product_moments' => [['start_seconds' => 0, 'end_seconds' => 3, 'moment' => 'Stable product close-up']],
                'recommended_cuts' => [['start_seconds' => 0, 'end_seconds' => 3, 'reason' => 'Stable product close-up']],
                'edit_plan' => ['timeline' => [['start_seconds' => 0, 'end_seconds' => 3, 'instruction' => 'Open on product.']]],
                'campaign_input_mode' => 'video_analysis',
                'has_source_video' => true,
                'has_edit_plan' => true,
                'auto_video_editor_eligible' => true,
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.input_payload.overlay_style', 'minimal_clean');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'ai_video_analysis',
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.input_payload.overlay_style', 'product_highlights');
    }

    public function test_auto_video_editor_rejects_invalid_audio_cleanup_and_edit_polish_settings(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, 'raw_product_video');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'edit_mode' => 'manual_edit',
                'source_video_asset_id' => $sourceVideo->id,
                'include_voiceover' => false,
                'source_audio_mode' => 'ai_denoise_magic',
                'voiceover_mix' => 'too_loud',
                'transition_style' => 'star_wipe',
                'motion_polish' => 'chaotic_spin',
                'clip_diversity' => 'repeat_everything',
                'end_screen' => 'fake_checkout',
                'overlay_style' => 'fake_hype_stickers',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source_audio_mode',
                'voiceover_mix',
                'transition_style',
                'motion_polish',
                'clip_diversity',
                'end_screen',
                'overlay_style',
            ]);
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
                'source_video_asset_id' => 999,
                'recommended_cuts' => [[
                    'start_seconds' => 0,
                    'end_seconds' => 3,
                    'reason' => 'Open on product.',
                ]],
                'edit_plan' => ['title' => 'Reusable edit plan', 'timeline' => [[
                    'start_seconds' => 0,
                    'end_seconds' => 3,
                    'instruction' => 'Open on product.',
                ]]],
                'narration_voice_script' => 'Reusable narration.',
                'campaign_input_mode' => 'video_analysis',
                'has_source_video' => true,
                'has_edit_plan' => true,
                'auto_video_editor_eligible' => true,
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
