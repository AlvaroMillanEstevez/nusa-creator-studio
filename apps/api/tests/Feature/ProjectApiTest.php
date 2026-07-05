<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_user_can_manage_projects_through_the_api(): void
    {
        $user = User::factory()->create();

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

    public function test_project_payloads_are_validated(): void
    {
        User::factory()->create();

        $this->postJson('/api/v1/projects', [
            'type' => 'unsupported_type',
            'status' => 'unknown',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type', 'status']);
    }

    public function test_a_development_user_is_created_when_the_database_has_no_users(): void
    {
        $this->postJson('/api/v1/projects', [
            'title' => 'First project',
            'type' => 'product_to_video',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('projects', 1);
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
