<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_states_and_access_helpers_are_consistent(): void
    {
        $admin = User::factory()->admin()->create();
        $internal = User::factory()->internalCreator()->create();
        $subscriber = User::factory()->subscriber()->create();
        $free = User::factory()->freeUser()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($internal->isInternalCreator());
        $this->assertTrue($internal->hasUnlimitedAccess());
        $this->assertSame('internal_unlimited', $internal->plan_key);
        $this->assertTrue($subscriber->isSubscriber());
        $this->assertSame('starter', $subscriber->plan_key);
        $this->assertSame('free_user', $free->role);
        $this->assertFalse($free->hasUnlimitedAccess());
    }

    public function test_local_command_promotes_an_existing_user_to_internal_unlimited_access(): void
    {
        $user = User::factory()->freeUser()->create(['email' => 'creator@example.test']);

        $this->artisan('nusa:user-access', [
            'email' => $user->email,
            '--role' => 'internal_creator',
            '--plan' => 'internal_unlimited',
            '--unlimited' => true,
        ])
            ->expectsOutput("Access updated for {$user->email}.")
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame('internal_creator', $user->role);
        $this->assertSame('internal_unlimited', $user->plan_key);
        $this->assertTrue($user->is_unlimited);
    }

    public function test_unlimited_user_generates_with_zero_balance_across_all_tools_without_a_charge(): void
    {
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);

        $projects = [
            [
                'type' => 'affiliate_campaign',
                'endpoint' => 'generate-campaign',
                'input_payload' => [
                    'product_name' => 'Desk light',
                    'product_description' => 'A compact light for creator desks.',
                    'target_audience' => 'Home creators',
                    'language' => 'english',
                    'tone' => 'natural',
                ],
            ],
            [
                'type' => 'video_enhancer',
                'endpoint' => 'generate-video-enhancer',
                'input_payload' => [
                    'video_title' => 'Desk setup',
                    'video_context' => 'A simple product demo.',
                    'spoken_summary' => 'A creator explains everyday use.',
                    'product_name' => 'Desk light',
                    'target_audience' => 'Home creators',
                    'language' => 'english',
                    'style' => 'natural',
                ],
            ],
            [
                'type' => 'product_to_video',
                'endpoint' => 'generate-product-video-script',
                'input_payload' => [
                    'product_name' => 'Desk light',
                    'product_description' => 'A compact light for creator desks.',
                    'key_benefits' => 'Portable and simple to set up.',
                    'target_audience' => 'Home creators',
                    'video_goal' => 'product_demo',
                    'video_style' => 'natural',
                    'duration_seconds' => 30,
                    'language' => 'english',
                ],
            ],
        ];

        $this->actingAs($user);

        foreach ($projects as $definition) {
            $project = Project::factory()->for($user)->create([
                'title' => 'Unlimited access test',
                'type' => $definition['type'],
                'input_payload' => $definition['input_payload'],
            ]);

            $response = $this
                ->postJson("/api/v1/projects/{$project->id}/{$definition['endpoint']}")
                ->assertCreated()
                ->assertJsonPath('data.credits_charged', 0)
                ->assertJsonPath('meta.credits_balance', 0);

            $generation = Generation::query()->findOrFail($response->json('data.id'));
            $transaction = CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->firstOrFail();

            $this->assertSame(0, $generation->credits_charged);
            $this->assertSame(CreditTransactionType::Usage, $transaction->type);
            $this->assertSame(0, $transaction->amount);
            $this->assertSame(0, $transaction->balance_after);
            $this->assertTrue($transaction->metadata['unlimited_access']);
            $this->assertStringContainsString('unlimited/internal access', $transaction->reason);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 3);
        $this->assertDatabaseCount('credit_transactions', 3);
    }
}
