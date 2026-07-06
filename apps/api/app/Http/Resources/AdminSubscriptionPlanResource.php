<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class AdminSubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'monthly_credits' => $this->monthly_credits,
            'max_projects_per_month' => $this->max_projects_per_month,
            'max_generations_per_month' => $this->max_generations_per_month,
            'is_public' => $this->is_public,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'is_internal' => $this->is_internal,
            'sort_order' => $this->sort_order,
            'cta_label' => $this->cta_label,
            'features' => $this->features ?? [],
            'limits' => $this->limits,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
