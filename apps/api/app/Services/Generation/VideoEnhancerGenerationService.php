<?php

namespace App\Services\Generation;

use App\Contracts\VideoEnhancerProvider;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Credits\CreditService;
use DomainException;
use Illuminate\Support\Facades\DB;

class VideoEnhancerGenerationService
{
    public const CREDIT_COST = 1;

    public function __construct(
        private readonly VideoEnhancerProvider $provider,
        private readonly CreditService $credits,
    ) {}

    public function generate(Project $project, User $user): Generation
    {
        if ($project->type !== 'video_enhancer') {
            throw new DomainException('Only video enhancer projects can use video enhancer generation.');
        }

        $requestPayload = $project->input_payload ?? [];
        $resultPayload = $this->provider->generate($requestPayload);
        $now = now();

        return DB::transaction(function () use (
            $project,
            $user,
            $requestPayload,
            $resultPayload,
            $now,
        ) {
            $generation = $project->generations()->create([
                'user_id' => $user->getKey(),
                'job_type' => 'generate_video_enhancer',
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
                amount: self::CREDIT_COST,
                reason: 'Mock video enhancer generation',
            );

            return $generation->refresh();
        });
    }
}
