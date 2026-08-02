<?php

namespace App\Services\Generation;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Services\Credits\CreditService;
use Illuminate\Support\Facades\DB;

class FashionTryOnGenerationProcessor
{
    public function __construct(
        private readonly FashionTryOnProviderManager $providers,
        private readonly CreditService $credits,
    ) {}

    public function process(int $generationId): void
    {
        $context = DB::transaction(function () use ($generationId): ?array {
            $generation = Generation::query()
                ->with('user')
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->job_type !== 'generate_fashion_try_on') {
                return null;
            }

            $input = $generation->input_payload ?? [];
            $hasPrediction = is_string($input['provider_prediction_id'] ?? null)
                && trim($input['provider_prediction_id']) !== '';

            if (! in_array($generation->status, [Generation::STATUS_QUEUED, 'pending'], true)
                && ! ($generation->status === Generation::STATUS_PROCESSING && $hasPrediction)) {
                return null;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_PROCESSING,
                'started_at' => $generation->started_at ?? now(),
                'error_message' => null,
            ])->save();

            return [
                'user' => $generation->user,
                'input' => array_merge($input, [
                    'generation_id' => $generation->getKey(),
                    'project_id' => $generation->project_id,
                    'user_id' => $generation->user_id,
                ]),
            ];
        });

        if ($context === null) {
            return;
        }

        $output = $this->providers->generateForUser($context['input'], $context['user']);

        DB::transaction(function () use ($generationId, $output): void {
            $generation = Generation::query()
                ->whereKey($generationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status !== Generation::STATUS_PROCESSING) {
                return;
            }

            $generation->forceFill([
                'status' => Generation::STATUS_COMPLETED,
                'cost_usd' => $this->providerCostUsd($output) ?? $generation->cost_usd,
                'output_payload' => $output,
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        });

        $this->applyPartialRefund($generationId, $output);
    }

    /** @param array<string, mixed> $output */
    private function providerCostUsd(array $output): ?float
    {
        $actual = data_get($output, 'provider_metadata.actual_provider_cost_usd');

        if (is_numeric($actual)) {
            return (float) $actual;
        }

        $estimated = data_get($output, 'provider_metadata.estimated_provider_cost_usd');

        return is_numeric($estimated) ? (float) $estimated : null;
    }

    /** @param array<string, mixed> $output */
    private function applyPartialRefund(int $generationId, array $output): void
    {
        $refund = $output['credit_refund'] ?? null;

        if (! is_array($refund) || ($refund['type'] ?? null) !== 'partial_output') {
            return;
        }

        $amount = is_numeric($refund['amount'] ?? null) ? (int) $refund['amount'] : 0;
        $metadata = is_array($refund['metadata'] ?? null) ? $refund['metadata'] : [];
        $reason = is_string($refund['reason'] ?? null)
            ? $refund['reason']
            : 'Partial refund for missing Fashion Try-On variations';

        $transaction = $this->credits->refundUsageAmount(
            generation: Generation::query()->findOrFail($generationId),
            requestedAmount: $amount,
            reason: $reason,
            metadata: $metadata + ['refund_scope' => 'fashion_try_on_partial_output'],
            auditZeroAmount: true,
        );

        $this->syncPartialRefundAccounting($generationId, $transaction);
    }

    private function syncPartialRefundAccounting(int $generationId, ?CreditTransaction $transaction): void
    {
        $generation = Generation::query()->findOrFail($generationId);
        $payload = is_array($generation->output_payload) ? $generation->output_payload : [];

        if ($payload === [] || ! is_array($payload['credit_refund'] ?? null)) {
            return;
        }

        $actualRefundAmount = $transaction instanceof CreditTransaction ? (int) $transaction->amount : 0;
        $netCreditCost = max(0, (int) $generation->credits_charged);

        data_set($payload, 'refunded_credit_amount', $actualRefundAmount);
        data_set($payload, 'net_credit_cost', $netCreditCost);
        data_set($payload, 'generation_summary.refunded_credit_amount', $actualRefundAmount);
        data_set($payload, 'generation_summary.net_credit_cost', $netCreditCost);
        data_set($payload, 'credit_refund.amount', $actualRefundAmount);
        data_set($payload, 'credit_refund.metadata.refunded_credit_amount', $actualRefundAmount);
        data_set($payload, 'credit_refund.metadata.actual_refunded_credit_amount', $actualRefundAmount);
        data_set($payload, 'credit_refund.metadata.net_credit_cost', $netCreditCost);

        $generation->forceFill(['output_payload' => $payload])->save();
    }
}
