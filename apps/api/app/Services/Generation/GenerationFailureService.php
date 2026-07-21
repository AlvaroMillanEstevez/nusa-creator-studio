<?php

namespace App\Services\Generation;

use App\Exceptions\AutoVideoEditor\AutoVideoEditorProviderException;
use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use App\Exceptions\VideoToCampaign\VideoToCampaignProviderException;
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
        $generation = DB::transaction(function () use ($generationId, $exception): ?Generation {
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

            $updates = [
                'status' => Generation::STATUS_FAILED,
                'error_message' => self::SAFE_ERROR_MESSAGE,
                'finished_at' => now(),
            ];
            $failurePayload = $this->safeFailurePayload($generation, $exception);

            if ($failurePayload !== null) {
                $updates['output_payload'] = $failurePayload;
            }

            $generation->forceFill($updates)->save();

            return $generation;
        });

        if ($generation !== null) {
            $this->credits->refundUsage(
                $generation,
                'Refund for failed generation',
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeFailurePayload(Generation $generation, ?Throwable $exception): ?array
    {
        if (! $exception instanceof VideoToCampaignProviderException && ! $exception instanceof AutoVideoEditorProviderException && ! $exception instanceof MotionAiVideoProviderException) {
            return null;
        }

        $metadata = [
            'provider' => $generation->provider ?: 'unknown',
            'mode' => 'failure',
            'failure_reason' => $exception->reason,
            'failure_stage' => is_string($exception->context['stage'] ?? null) ? $exception->context['stage'] : null,
            'endpoint_type' => is_string($exception->context['endpoint_type'] ?? null) ? $exception->context['endpoint_type'] : null,
            'provider_transport' => is_string($exception->context['provider_transport'] ?? null) ? $exception->context['provider_transport'] : null,
            'safe_error_category' => is_string($exception->context['safe_error_category'] ?? null) ? $exception->context['safe_error_category'] : null,
            'http_status' => is_numeric($exception->context['http_status'] ?? null) ? (int) $exception->context['http_status'] : null,
            'render_id' => is_string($exception->context['render_id'] ?? null) ? $exception->context['render_id'] : null,
        ];

        return [
            'provider_metadata' => array_filter($metadata, fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }
}
