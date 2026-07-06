<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class CurrentSubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'monthly_credits' => $this->monthly_credits,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'features' => $this->features ?? [],
        ];
    }
}
