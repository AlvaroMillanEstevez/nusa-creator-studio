<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'job_type' => $this->job_type,
            'provider' => $this->provider,
            'status' => $this->status,
            'cost_usd' => $this->cost_usd,
            'credits_charged' => $this->credits_charged,
            'input_payload' => $this->input_payload,
            'output_payload' => $this->output_payload,
            'error_message' => $this->status === Generation::STATUS_FAILED
                ? $this->error_message
                : null,
            // Temporary response aliases for existing clients.
            'request_payload' => $this->input_payload,
            'result_payload' => $this->output_payload,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
