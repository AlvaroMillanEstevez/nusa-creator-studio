<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'plan_key' => $this->plan_key,
            'is_unlimited' => $this->is_unlimited,
            'monthly_credit_allowance' => $this->monthly_credit_allowance,
            'credits_balance' => $this->credits_balance,
            'projects_count' => (int) $this->projects_count,
            'generations_count' => (int) $this->generations_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
