<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolCreditCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_generation_flows_charge_the_configured_tool_credit_cost(): void
    {
        $user = User::factory()->create(['credits_balance' => 10]);

        foreach ($this->toolDefinitions() as $definition) {
            CreatorTool::query()->where('key', $definition['key'])->update(['credit_cost' => 3]);

            $project = Project::factory()->for($user)->create([
                'type' => $definition['type'],
                'input_payload' => $definition['input_payload'],
            ]);

            $response = $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/{$definition['endpoint']}")
                ->assertCreated()
                ->assertJsonPath('data.credits_charged', 3);

            $generation = Generation::query()->findOrFail($response->json('data.id'));
            $transaction = CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->firstOrFail();

            $this->assertSame(3, $generation->credits_charged);
            $this->assertSame(CreditTransactionType::Usage, $transaction->type);
            $this->assertSame(3, $transaction->amount);
        }

        $this->assertSame(1, $user->refresh()->credits_balance);
    }

    public function test_insufficient_configured_tool_credits_fail_without_a_charge(): void
    {
        $user = User::factory()->create(['credits_balance' => 2]);
        CreatorTool::query()->where('key', 'affiliate_campaign')->update(['credit_cost' => 3]);
        $project = $this->projectFor($user, $this->toolDefinitions()[0]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 2)
            ->assertJsonPath('required_credits', 3);

        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_unlimited_user_pays_zero_for_a_tool_that_costs_three_credits(): void
    {
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        CreatorTool::query()->where('key', 'affiliate_campaign')->update(['credit_cost' => 3]);
        $project = $this->projectFor($user, $this->toolDefinitions()[0]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0)
            ->assertJsonPath('meta.credits_balance', 0);

        $generation = Generation::query()->findOrFail($response->json('data.id'));
        $transaction = CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->firstOrFail();

        $this->assertSame(0, $generation->credits_charged);
        $this->assertSame(0, $transaction->amount);
        $this->assertTrue($transaction->metadata['unlimited_access']);
    }

    public function test_zero_cost_tool_generates_without_reducing_normal_user_credits(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        CreatorTool::query()->where('key', 'affiliate_campaign')->update(['credit_cost' => 0]);
        $project = $this->projectFor($user, $this->toolDefinitions()[0]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(0, $transaction->amount);
        $this->assertSame(0, $transaction->balance_after);
        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'key' => 'affiliate_campaign',
                'type' => 'affiliate_campaign',
                'endpoint' => 'generate-campaign',
                'input_payload' => [
                    'product_name' => 'Creator light',
                    'product_description' => 'A compact light for creator desks.',
                    'target_audience' => 'Home creators',
                    'language' => 'english',
                    'tone' => 'natural',
                ],
            ],
            [
                'key' => 'video_enhancer',
                'type' => 'video_enhancer',
                'endpoint' => 'generate-video-enhancer',
                'input_payload' => [
                    'video_title' => 'Desk setup',
                    'video_context' => 'A simple product demo.',
                    'spoken_summary' => 'A creator explains everyday use.',
                    'product_name' => 'Creator light',
                    'target_audience' => 'Home creators',
                    'language' => 'english',
                    'style' => 'natural',
                ],
            ],
            [
                'key' => 'product_to_video',
                'type' => 'product_to_video',
                'endpoint' => 'generate-product-video-script',
                'input_payload' => [
                    'product_name' => 'Creator light',
                    'product_description' => 'A compact light for creator desks.',
                    'key_benefits' => 'Portable and easy to use.',
                    'target_audience' => 'Home creators',
                    'video_goal' => 'product_demo',
                    'video_style' => 'natural',
                    'duration_seconds' => 30,
                    'language' => 'english',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function projectFor(User $user, array $definition): Project
    {
        return Project::factory()->for($user)->create([
            'type' => $definition['type'],
            'input_payload' => $definition['input_payload'],
        ]);
    }
}
