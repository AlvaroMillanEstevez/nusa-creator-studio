<?php

namespace App\Http\Resources;

use App\Models\CreatorTool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreatorTool
 */
class AdminCreatorToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'category' => $this->category,
            'status' => $this->status,
            'route' => $this->route,
            'credit_cost' => $this->credit_cost,
            'credit_cost_label' => $this->credit_cost_label,
            'badge_label' => $this->badge_label,
            'is_recommended' => $this->is_recommended,
            'is_visible' => $this->is_visible,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
