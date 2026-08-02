<?php

namespace App\Services\Credits;

use App\Enums\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function chargeUsage(
        User $user,
        Generation $generation,
        int $amount,
        string $reason,
    ): CreditTransaction {
        if ($amount < 0) {
            throw new DomainException('Platform credit charges cannot be negative.');
        }

        return DB::transaction(function () use ($user, $generation, $amount, $reason) {
            $lockedGeneration = Generation::query()
                ->whereKey($generation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingCharge = $lockedGeneration->creditTransactions()
                ->where('type', CreditTransactionType::Usage->value)
                ->first();

            if ($existingCharge !== null) {
                return $existingCharge;
            }

            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isUnlimited = $lockedUser->hasUnlimitedAccess();
            $chargedAmount = $isUnlimited ? 0 : $amount;

            if ($lockedUser->credits_balance < $chargedAmount) {
                throw new InsufficientCreditsException(
                    availableCredits: $lockedUser->credits_balance,
                    requiredCredits: $chargedAmount,
                );
            }

            $balanceAfter = $lockedUser->credits_balance - $chargedAmount;

            if ($chargedAmount > 0) {
                $lockedUser->forceFill(['credits_balance' => $balanceAfter])->save();
            }

            $lockedGeneration->forceFill(['credits_charged' => $chargedAmount])->save();

            return $lockedUser->creditTransactions()->create([
                'generation_id' => $lockedGeneration->getKey(),
                'type' => CreditTransactionType::Usage,
                'amount' => $chargedAmount,
                'balance_after' => $balanceAfter,
                'reason' => $isUnlimited
                    ? "{$reason} (unlimited/internal access)"
                    : $reason,
                'metadata' => $isUnlimited ? ['unlimited_access' => true, 'configured_usage_amount' => $amount] : ['configured_usage_amount' => $amount],
            ]);
        });
    }

    public function refundUsage(Generation $generation, string $reason): ?CreditTransaction
    {
        return $this->refundUsageAmount(
            generation: $generation,
            requestedAmount: $generation->credits_charged,
            reason: $reason,
            metadata: [
                'failed_generation_id' => $generation->getKey(),
                'refund_reason' => 'internal_generation_failure',
                'refund_scope' => 'full_generation_failure',
            ],
            auditZeroAmount: false,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function refundUsageAmount(
        Generation $generation,
        int $requestedAmount,
        string $reason,
        array $metadata = [],
        bool $auditZeroAmount = false,
    ): ?CreditTransaction {
        if ($requestedAmount < 0) {
            throw new DomainException('Platform credit refunds cannot be negative.');
        }

        return DB::transaction(function () use ($generation, $requestedAmount, $reason, $metadata, $auditZeroAmount) {
            $lockedGeneration = Generation::query()
                ->whereKey($generation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $refundScope = is_string($metadata['refund_scope'] ?? null)
                ? $metadata['refund_scope']
                : 'usage_refund';

            $existingRefund = $lockedGeneration->creditTransactions()
                ->where('type', CreditTransactionType::Refund->value)
                ->get()
                ->first(function (CreditTransaction $transaction) use ($refundScope): bool {
                    $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

                    return ($metadata['refund_scope'] ?? null) === $refundScope;
                });

            if ($existingRefund !== null) {
                return $existingRefund;
            }

            $refundAmount = min($requestedAmount, max(0, (int) $lockedGeneration->credits_charged));

            if ($refundAmount === 0 && ! $auditZeroAmount) {
                return null;
            }

            $lockedUser = User::query()
                ->whereKey($lockedGeneration->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = $lockedUser->credits_balance + $refundAmount;

            if ($refundAmount > 0) {
                $lockedUser->forceFill(['credits_balance' => $balanceAfter])->save();
            }

            $lockedGeneration->forceFill([
                'credits_charged' => max(0, (int) $lockedGeneration->credits_charged - $refundAmount),
            ])->save();

            return $lockedUser->creditTransactions()->create([
                'generation_id' => $lockedGeneration->getKey(),
                'type' => CreditTransactionType::Refund,
                'amount' => $refundAmount,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
                'metadata' => array_filter(array_merge($metadata, [
                    'requested_refund_amount' => $requestedAmount,
                    'refunded_credit_amount' => $refundAmount,
                ]), fn (mixed $value): bool => $value !== null),
            ]);
        });
    }
}
