<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_actions_returns_available_and_contextual_actions(): void
    {
        $user = User::factory()->create();
        $project = $this->campaignProject($user);
        $sourceGeneration = Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'job_type' => 'generate_affiliate_campaign',
                'status' => Generation::STATUS_COMPLETED,
                'output_payload' => [
                    'voiceover_ready_script' => 'Use this campaign script for voiceover.',
                ],
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->assertJsonPath('data.0.key', 'generate_campaign')
            ->assertJsonPath('data.0.status', 'available');

        $actions = collect($response->json('data'));
        $voiceoverAction = $actions->firstWhere('key', 'generate_voiceover_from_campaign');

        $this->assertSame('available', $voiceoverAction['status']);
        $this->assertSame('generation', $voiceoverAction['required_source']);
        $this->assertSame($sourceGeneration->id, $voiceoverAction['source_generation_id']);
        $this->assertSame('/api/v1/projects/'.$project->id.'/generate-voiceover', $voiceoverAction['action_endpoint']);

        $this->assertSame('coming_soon', $actions->firstWhere('key', 'avatar_presenter')['status']);
        $this->assertSame('needs_assets', $actions->firstWhere('key', 'start_fashion_try_on')['status']);
        $this->assertSame('needs_assets', $actions->firstWhere('key', 'start_motion_video')['status']);
    }

    public function test_project_actions_reflect_asset_requirements(): void
    {
        $user = User::factory()->create();
        $project = $this->campaignProject($user);
        MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'model_reference',
        ]);
        MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'garment_reference',
        ]);
        MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
        ]);

        $actions = collect($this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk()
            ->json('data'));

        $this->assertSame('available', $actions->firstWhere('key', 'start_fashion_try_on')['status']);
        $this->assertSame('available', $actions->firstWhere('key', 'start_motion_video')['status']);
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
