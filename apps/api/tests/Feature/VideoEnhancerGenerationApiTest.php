<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoEnhancerGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_video_enhancer_generation_creates_completed_result(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->videoEnhancerProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer");

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_video_enhancer')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonCount(10, 'data.result_payload.hashtags')
            ->assertJsonStructure([
                'data' => [
                    'result_payload' => [
                        'improved_caption',
                        'subtitle_plan' => [['timestamp', 'text']],
                        'cut_plan' => [['timestamp', 'edit_direction', 'reason']],
                        'on_screen_text',
                        'posting_text',
                        'hashtags',
                        'cta',
                        'notes',
                    ],
                ],
                'meta' => ['credits_balance'],
            ]);

        $generationId = $response->json('data.id');

        $this->assertDatabaseHas('generations', [
            'id' => $generationId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_video_enhancer',
            'provider' => 'mock',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame($project->input_payload, $generation->request_payload);
        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
    }

    public function test_video_enhancer_charges_one_credit_and_records_positive_usage(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->videoEnhancerProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $generationId = $response->json('data.id');
        $transaction = CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(2, $transaction->balance_after);
        $this->assertSame('Mock video enhancer generation', $transaction->reason);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_never_creates_a_negative_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $project = $this->videoEnhancerProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath(
                'message',
                'You do not have enough platform credits to generate this caption pack.',
            )
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('required_credits', 1);

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_non_video_enhancer_projects_cannot_use_the_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()
            ->for($user)
            ->create(['type' => 'affiliate_campaign']);

        $this->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath(
                'message',
                'Only video enhancer projects can use video enhancer generation.',
            );

        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_mock_video_enhancer_supports_all_three_languages(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);

        foreach ([
            'english' => 'A natural, honest look',
            'indonesian' => 'Ulasan natural dan jujur',
            'spanish' => 'Una mirada natural y honesta',
        ] as $language => $expectedCaption) {
            $project = $this->videoEnhancerProject($user, $language);

            $caption = $this
                ->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
                ->assertCreated()
                ->json('data.result_payload.improved_caption');

            $this->assertIsString($caption);
            $this->assertStringContainsString($expectedCaption, $caption);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    public function test_spanish_video_enhancer_preserves_unicode_from_the_brief(): void
    {
        $user = User::factory()->create(['credits_balance' => 1]);
        $project = $this->videoEnhancerProject($user, 'spanish', [
            'video_title' => 'Vídeo rápido: ¿funciona?',
            'product_name' => 'Cámara Pequeña Ñandú',
            'video_context' => 'Demostración práctica para creación de contenido.',
            'spoken_summary' => '¡Mira cómo mejora la edición sin promesas exageradas!',
            'target_audience' => 'Pequeños creadores en España',
            'style' => 'energetic',
        ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
            ->assertCreated()
            ->assertJsonPath(
                'data.result_payload.subtitle_plan.0.text',
                'Vídeo rápido: ¿funciona?: conoce Cámara Pequeña Ñandú',
            )
            ->assertJsonPath(
                'data.result_payload.subtitle_plan.1.text',
                '¡Mira cómo mejora la edición sin promesas exageradas!',
            )
            ->assertJsonPath(
                'data.result_payload.hashtags.0',
                '#CámaraPequeñaÑandú',
            );

        $json = json_encode(
            $response->json('data.result_payload'),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        foreach (['á', 'ó', 'ñ', 'Ñ', '¿', '¡'] as $character) {
            $this->assertStringContainsString($character, $json);
        }

        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $json);
        }
    }

    public function test_user_cannot_generate_another_users_video_enhancer_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->videoEnhancerProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-enhancer")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame(20, $otherUser->refresh()->credits_balance);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function videoEnhancerProject(
        User $user,
        string $language = 'english',
        array $overrides = [],
    ): Project {
        return Project::factory()
            ->for($user)
            ->create([
                'title' => 'Desk setup product demo',
                'type' => 'video_enhancer',
                'status' => 'draft',
                'input_payload' => array_merge([
                    'video_title' => 'Desk setup product demo',
                    'video_context' => 'A creator demonstrates the product at a desk.',
                    'spoken_summary' => 'The creator explains the setup and everyday use.',
                    'product_name' => 'Portable key light',
                    'target_audience' => 'Creators improving small filming spaces',
                    'language' => $language,
                    'style' => 'natural',
                ], $overrides),
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);
    }
}
