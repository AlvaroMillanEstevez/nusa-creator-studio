<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_projects_through_the_api(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $createResponse = $this->postJson('/api/v1/projects', [
            'title' => 'Summer affiliate launch',
            'type' => 'affiliate_campaign',
            'input_payload' => ['product_name' => 'Travel bottle'],
            'settings' => ['language' => 'id'],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'draft');

        $projectId = $createResponse->json('data.id');

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projectId);

        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Summer affiliate launch');

        $this->patchJson("/api/v1/projects/{$projectId}", [
            'title' => 'Updated affiliate launch',
            'status' => 'processing',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated affiliate launch')
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'user_id' => $user->id,
            'status' => 'processing',
        ]);

        $this->deleteJson("/api/v1/projects/{$projectId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $projectId]);
    }

    public function test_project_text_round_trips_unicode_without_mojibake(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $title = 'Campaña ÁÉÍÓÚ áéíóú para España: ñ Ñ ¿? ¡!';
        $description = 'Diseñado para creadores que necesitan automatización.';
        $indonesian = 'Konten untuk kreator Indonesia tetap cepat, jujur, dan mudah dipahami.';

        $createResponse = $this->postJson('/api/v1/projects', [
            'title' => $title,
            'type' => 'affiliate_campaign',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => $title,
                'product_description' => $description,
                'target_audience' => 'Pequeños creadores en España',
                'language' => 'spanish',
                'tone' => 'natural',
                'indonesian_sample' => $indonesian,
            ],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.input_payload.product_description', $description)
            ->assertJsonPath('data.input_payload.indonesian_sample', $indonesian);

        $projectId = $createResponse->json('data.id');
        $fetchResponse = $this->getJson("/api/v1/projects/{$projectId}");

        $fetchResponse
            ->assertOk()
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.input_payload.product_description', $description)
            ->assertJsonPath('data.input_payload.indonesian_sample', $indonesian);

        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $fetchResponse->getContent());
        }
    }

    public function test_generic_workspace_project_can_be_created_and_listed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/projects', [
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
                'notes' => 'Keep claims practical.',
            ],
            'settings' => [
                'platform' => 'tiktok',
                'format' => 'vertical_9_16',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'workspace')
            ->assertJsonPath('data.input_payload.content_idea', 'Compact creator desk lighting kit');

        $projectId = $response->json('data.id');

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $projectId)
            ->assertJsonPath('data.0.type', 'workspace');
    }

    public function test_project_payloads_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/projects', [
            'type' => 'unsupported_type',
            'status' => 'unknown',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type', 'status']);
    }

    public function test_unauthenticated_user_cannot_create_a_project(): void
    {
        $this->postJson('/api/v1/projects', [
            'title' => 'First project',
            'type' => 'product_to_video',
        ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_an_authenticated_user_cannot_access_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertNotFound();
    }
}
