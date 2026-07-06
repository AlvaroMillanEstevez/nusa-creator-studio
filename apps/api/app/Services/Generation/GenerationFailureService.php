<?php

namespace App\Services\Generation;

use App\Models\Generation;
use App\Services\Credits\CreditService;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerationFailureService
{
    public const SAFE_ERROR_MESSAGE = 'Generation failed due to an internal processing error.';

    public function __construct(private readonly CreditService $credits) {}

    public function fail(int $generationId, ?Throwable $exception = null): void
    {
        $generation = DB::transaction(function () use ($generationId): ?Generation {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->first();

            if ($generation === null || in_array($generation->status, [
                Generation::STATUS_COMPLETED,
                Generation::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_FAILED,
                'error_message' => self::SAFE_ERROR_MESSAGE,
                'finished_at' => now(),
            ])->save();

            return $generation;
        });

        if ($generation !== null) {
            $this->credits->refundUsage(
                $generation,
                'Refund for failed generation',
            );
        }
    }
}
