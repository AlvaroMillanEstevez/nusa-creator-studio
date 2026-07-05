<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_mock_generation_creates_a_completed_generation_and_result(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->affiliateProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign");

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_affiliate_campaign')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonCount(5, 'data.result_payload.hooks')
            ->assertJsonCount(10, 'data.result_payload.hashtags')
            ->assertJsonStructure([
                'data' => [
                    'result_payload' => [
                        'hooks',
                        'script' => ['title', 'duration_seconds', 'scenes'],
                        'caption',
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
            'job_type' => 'generate_affiliate_campaign',
            'provider' => 'mock',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
        $this->assertIsString($generation->result_payload['caption']);
        $this->assertIsString($generation->result_payload['cta']);
    }

    public function test_successful_generation_charges_one_credit_and_records_positive_usage(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->affiliateProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $generationId = $response->json('data.id');
        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(2, $transaction->balance_after);
        $this->assertSame('Mock affiliate campaign generation', $transaction->reason);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_returns_a_clear_error_without_negative_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $project = $this->affiliateProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('required_credits', 1)
            ->assertJsonPath(
                'message',
                'You do not have enough platform credits to generate this campaign.',
            );

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_non_affiliate_projects_cannot_generate_campaign_content(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()
            ->for($user)
            ->create(['type' => 'video_enhancer']);

        $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath(
                'message',
                'Only affiliate campaign projects can use campaign generation.',
            );

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $user->refresh()->credits_balance);
    }

    public function test_mock_generation_supports_the_three_campaign_languages(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);

        foreach ([
            'english' => 'Before you scroll',
            'indonesian' => 'Sebelum scroll',
            'spanish' => '¿Quieres crear contenido rápido?',
        ] as $language => $expectedHook) {
            $project = $this->affiliateProject($user, $language);

            $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
                ->assertCreated();

            $hook = $response->json('data.result_payload.hooks.0');

            $this->assertIsString($hook);
            $this->assertStringContainsString($expectedHook, $hook);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    public function test_spanish_mock_generation_preserves_unicode_without_mojibake(): void
    {
        $user = User::factory()->create(['credits_balance' => 1]);
        $product = 'Cámara Ágil Á É Í Ó Ú, piñata Ñandú';
        $description = 'Diseñado para creadores que necesitan automatización.';
        $audience = 'Pequeños creadores en España';

        $project = Project::factory()
            ->for($user)
            ->create([
                'title' => 'Campaña para pequeños creadores en España',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
                'input_payload' => [
                    'product_name' => $product,
                    'product_description' => $description,
                    'target_audience' => $audience,
                    'language' => 'spanish',
                    'tone' => 'problem_solution',
                ],
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign");

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.result_payload.hooks.0',
                "¿Quieres crear contenido rápido? Mira de cerca {$product}.",
            )
            ->assertJsonPath(
                'data.result_payload.hooks.3',
                '¡Una idea sencilla para mostrar un producto que encaja en tu rutina diaria!',
            )
            ->assertJsonPath(
                'data.result_payload.script.scenes.1.on_screen_text',
                '¿Para quién es?',
            )
            ->assertJsonPath(
                'data.result_payload.script.scenes.1.visual_direction',
                "Muestra honestamente este detalle proporcionado por el creador: {$description}.",
            )
            ->assertJsonPath('data.result_payload.hashtags.0', '#CámaraÁgilÁÉÍÓÚpiñataÑandú')
            ->assertJsonPath('data.result_payload.hashtags.2', '#ReseñaDeProducto');

        $result = $response->json('data.result_payload');
        $unicodeJson = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach (['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', '¿', '?', '¡', '!'] as $character) {
            $this->assertStringContainsString($character, $unicodeJson);
        }

        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $unicodeJson);
        }
    }

    public function test_user_cannot_generate_another_users_campaign(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->affiliateProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame(20, $otherUser->refresh()->credits_balance);
    }

    private function affiliateProject(User $user, string $language = 'english'): Project
    {
        return Project::factory()
            ->for($user)
            ->create([
                'title' => 'Portable smoothie blender',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
                'input_payload' => [
                    'product_name' => 'Portable smoothie blender',
                    'product_description' => 'A rechargeable blender for quick smoothies.',
                    'target_audience' => 'Busy students who want healthy breakfasts.',
                    'language' => $language,
                    'tone' => 'problem_solution',
                ],
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);
    }
}
