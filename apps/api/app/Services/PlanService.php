<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PlanService
{
    public function findByKey(string $key): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('key', $key)->first();
    }

    public function findByKeyOrFail(string $key): SubscriptionPlan
    {
        $plan = $this->findByKey($key);

        if (! $plan) {
            throw (new ModelNotFoundException)->setModel(SubscriptionPlan::class, [$key]);
        }

        return $plan;
    }

    public function syncMonthlyCreditAllowance(User $user, ?string $planKey = null): User
    {
        $plan = $this->findByKeyOrFail($planKey ?? $user->plan_key);

        $user->forceFill([
            'monthly_credit_allowance' => $plan->monthly_credits,
        ])->save();

        return $user->refresh();
    }
}
