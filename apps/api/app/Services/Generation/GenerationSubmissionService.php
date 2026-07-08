<?php

namespace App\Services\Generation;

use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Credits\CreditService;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerationSubmissionService
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly GenerationFailureService $failures,
    ) {}

    /**
     * @param  Closure(int): mixed  $dispatch
     * @param  array<string, mixed>|null  $inputPayload
     */
    public function submit(
        Project $project,
        User $user,
        string $jobType,
        int $creditCost,
        string $creditReason,
        Closure $dispatch,
        ?array $inputPayload = null,
    ): Generation {
        $generation = DB::transaction(function () use (
            $project,
            $user,
            $jobType,
            $creditCost,
            $creditReason,
            $inputPayload,
        ): Generation {
            $generation = $project->generations()->create([
                'user_id' => $user->getKey(),
                'job_type' => $jobType,
                'provider' => 'mock',
                'status' => Generation::STATUS_QUEUED,
                'cost_usd' => 0,
                'credits_charged' => 0,
                'input_payload' => $inputPayload ?? $project->input_payload ?? [],
                'output_payload' => null,
                'error_message' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);

            $this->credits->chargeUsage(
                user: $user,
                generation: $generation,
                amount: $creditCost,
                reason: $creditReason,
            );

            return $generation;
        });

        try {
            $dispatch($generation->getKey());
        } catch (Throwable $exception) {
            $this->failures->fail($generation->getKey(), $exception);

            throw $exception;
        }

        return $generation->refresh();
    }
}
