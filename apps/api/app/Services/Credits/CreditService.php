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
                'metadata' => $isUnlimited ? ['unlimited_access' => true] : null,
            ]);
        });
    }

    public function refundUsage(Generation $generation, string $reason): ?CreditTransaction
    {
        return DB::transaction(function () use ($generation, $reason) {
            $lockedGeneration = Generation::query()
                ->whereKey($generation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingRefund = $lockedGeneration->creditTransactions()
                ->where('type', CreditTransactionType::Refund->value)
                ->first();

            if ($existingRefund !== null) {
                return $existingRefund;
            }

            $refundAmount = $lockedGeneration->credits_charged;

            if ($refundAmount === 0) {
                return null;
            }

            $lockedUser = User::query()
                ->whereKey($lockedGeneration->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = $lockedUser->credits_balance + $refundAmount;
            $lockedUser->forceFill(['credits_balance' => $balanceAfter])->save();

            return $lockedUser->creditTransactions()->create([
                'generation_id' => $lockedGeneration->getKey(),
                'type' => CreditTransactionType::Refund,
                'amount' => $refundAmount,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
                'metadata' => [
                    'failed_generation_id' => $lockedGeneration->getKey(),
                    'refund_reason' => 'internal_generation_failure',
                ],
            ]);
        });
    }
}
