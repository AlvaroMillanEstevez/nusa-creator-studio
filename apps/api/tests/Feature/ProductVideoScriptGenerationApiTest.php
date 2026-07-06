<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVideoScriptGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_generation_creates_a_completed_script_pack(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->productVideoProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-product-video-script");

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_product_video_script')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonCount(4, 'data.result_payload.storyboard')
            ->assertJsonCount(10, 'data.result_payload.hashtags')
            ->assertJsonStructure([
                'data' => [
                    'result_payload' => [
                        'concept_title',
                        'video_angle',
                        'storyboard' => [[
                            'scene_number',
                            'timestamp',
                            'scene_goal',
                            'visual_direction',
                            'voiceover',
                            'on_screen_text',
                        ]],
                        'shot_list',
                        'asset_checklist',
                        'caption',
                        'hashtags',
                        'cta',
                        'editing_notes',
                        'compliance_notes',
                    ],
                ],
                'meta' => ['credits_balance'],
            ]);

        $generationId = $response->json('data.id');

        $this->assertDatabaseHas('generations', [
            'id' => $generationId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_product_video_script',
            'provider' => 'mock',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame($project->input_payload, $generation->request_payload);
        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
    }

    public function test_generation_charges_one_credit_and_records_positive_usage(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->productVideoProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(2, $transaction->balance_after);
        $this->assertSame('Mock product-to-video script generation', $transaction->reason);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_never_create_a_negative_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $project = $this->productVideoProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('required_credits', 1)
            ->assertJsonPath(
                'message',
                'You do not have enough platform credits to generate this video script pack.',
            );

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_non_product_to_video_projects_cannot_use_the_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'video_enhancer']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath(
                'message',
                'Only product-to-video projects can generate video script packs.',
            );

        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_mock_provider_supports_all_three_languages(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);

        foreach ([
            'english' => 'production plan',
            'indonesian' => 'rencana produksi',
            'spanish' => 'plan de producción',
        ] as $language => $expected) {
            $project = $this->productVideoProject($user, $language);
            $title = $this
                ->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
                ->assertCreated()
                ->json('data.result_payload.concept_title');

            $this->assertIsString($title);
            $this->assertStringContainsString($expected, $title);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    public function test_spanish_input_remains_valid_in_the_result(): void
    {
        $user = User::factory()->create(['credits_balance' => 1]);
        $project = $this->productVideoProject($user, 'spanish', [
            'product_name' => 'Cámara Pequeña Ñandú',
            'product_description' => 'Diseñada para creación rápida y práctica.',
            'key_benefits' => 'Iluminación portátil, tamaño pequeño y edición ágil.',
            'target_audience' => 'Pequeños creadores en España',
            'video_style' => 'storytelling',
            'duration_seconds' => 45,
        ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
            ->assertCreated()
            ->assertJsonPath(
                'data.result_payload.concept_title',
                'Cámara Pequeña Ñandú: plan de producción storytelling de 45 segundos',
            )
            ->assertJsonPath('data.result_payload.hashtags.0', '#CámaraPequeñaÑandú')
            ->assertJsonPath(
                'data.result_payload.storyboard.2.visual_direction',
                'Graba dos o tres primeros planos que apoyen estos beneficios: Iluminación portátil, tamaño pequeño y edición ágil.',
            );

        $json = json_encode(
            $response->json('data.result_payload'),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        foreach (['á', 'ó', 'ñ', 'Ñ'] as $character) {
            $this->assertStringContainsString($character, $json);
        }

        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $json);
        }
    }

    public function test_user_cannot_generate_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->productVideoProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-product-video-script")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame(20, $otherUser->refresh()->credits_balance);
    }

    /**
     * @param  array<string, string|int>  $overrides
     */
    private function productVideoProject(
        User $user,
        string $language = 'english',
        array $overrides = [],
    ): Project {
        $this->actingAs($user);

        return Project::factory()
            ->for($user)
            ->create([
                'title' => 'Portable key light',
                'type' => 'product_to_video',
                'status' => 'draft',
                'input_payload' => array_merge([
                    'product_name' => 'Portable key light',
                    'product_description' => 'A small light for creator desk setups.',
                    'key_benefits' => 'Portable size, adjustable light and simple setup.',
                    'target_audience' => 'Creators filming in small rooms',
                    'video_goal' => 'product_demo',
                    'video_style' => 'natural',
                    'duration_seconds' => 30,
                    'language' => $language,
                ], $overrides),
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);
    }
}
