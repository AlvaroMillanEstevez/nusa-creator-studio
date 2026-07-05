<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_entities_are_related_and_cast_their_structured_data(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create([
                'input_payload' => ['product' => 'Travel bottle'],
                'settings' => ['language' => 'id'],
            ]);

        $mediaAsset = MediaAsset::factory()
            ->for($user)
            ->for($project)
            ->create(['metadata' => ['width' => 1080]]);

        $generation = Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'request_payload' => ['prompt' => 'Create a product hook'],
                'result_payload' => ['text' => 'A sample hook'],
            ]);

        $transaction = CreditTransaction::factory()
            ->for($user)
            ->for($generation)
            ->create([
                'type' => CreditTransactionType::Usage,
                'amount' => 5,
                'balance_after' => 95,
                'metadata' => ['source' => 'generation'],
            ]);

        $this->assertTrue($project->user->is($user));
        $this->assertTrue($project->mediaAssets->contains($mediaAsset));
        $this->assertTrue($project->generations->contains($generation));
        $this->assertTrue($generation->creditTransactions->contains($transaction));
        $this->assertTrue($transaction->generation->is($generation));

        $this->assertSame(['product' => 'Travel bottle'], $project->input_payload);
        $this->assertSame(['width' => 1080], $mediaAsset->metadata);
        $this->assertSame(['text' => 'A sample hook'], $generation->result_payload);
        $this->assertSame(['source' => 'generation'], $transaction->metadata);
    }
}
