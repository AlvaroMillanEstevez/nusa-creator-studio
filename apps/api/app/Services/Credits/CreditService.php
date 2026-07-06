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

            $generation->forceFill(['credits_charged' => $chargedAmount])->save();

            return $lockedUser->creditTransactions()->create([
                'generation_id' => $generation->getKey(),
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
}
