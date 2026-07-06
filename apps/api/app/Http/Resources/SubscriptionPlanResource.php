<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'monthly_credits' => $this->monthly_credits,
            'is_featured' => $this->is_featured,
            'cta_label' => $this->cta_label,
            'features' => $this->features ?? [],
            'limits' => $this->limits,
            'sort_order' => $this->sort_order,
        ];
    }
}
