<?php

namespace App\Services\Generation;

use App\Contracts\ProductVideoScriptProvider;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\ToolAvailabilityService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProductVideoScriptGenerationService
{
    public function __construct(
        private readonly ProductVideoScriptProvider $provider,
        private readonly CreditService $credits,
        private readonly ToolAvailabilityService $tools,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'product_to_video') {
            throw new DomainException('Only product-to-video projects can generate video script packs.');
        }

        $creditCost = $this->tools->creditCost('product_to_video');

        $requestPayload = $project->input_payload ?? [];
        $resultPayload = $this->provider->generate($requestPayload);
        $now = now();

        return DB::transaction(function () use (
            $project,
            $user,
            $requestPayload,
            $resultPayload,
            $now,
            $creditCost,
        ) {
            $generation = $project->generations()->create([
                'user_id' => $user->getKey(),
                'job_type' => 'generate_product_video_script',
                'provider' => 'mock',
                'status' => 'completed',
                'cost_usd' => 0,
                'credits_charged' => 0,
                'request_payload' => $requestPayload,
                'result_payload' => $resultPayload,
                'started_at' => $now,
                'finished_at' => $now,
            ]);

            $this->credits->chargeUsage(
                user: $user,
                generation: $generation,
                amount: $creditCost,
                reason: 'Mock product-to-video script generation',
            );

            return $generation->refresh();
        });
    }
}
