<?php

namespace App\Http\Resources;

use App\Models\CreatorTool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreatorTool
 */
class CreatorToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'category' => $this->category,
            'service_tier' => $this->service_tier,
            'output_type' => $this->output_type,
            'implementation_stage' => $this->implementation_stage,
            'status' => $this->status,
            'route' => $this->route,
            'credit_cost' => $this->credit_cost,
            'credit_cost_label' => $this->credit_cost_label,
            'badge_label' => $this->badge_label,
            'is_recommended' => $this->is_recommended,
            'sort_order' => $this->sort_order,
        ];
    }
}
