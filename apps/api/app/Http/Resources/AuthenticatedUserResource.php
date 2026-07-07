<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
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
            'preferred_locale' => $this->preferred_locale,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'plan_key' => $this->plan_key,
            'plan_label' => $this->planLabel(),
            'is_unlimited' => $this->is_unlimited,
            'access_label' => $this->accessLabel(),
            'monthly_credit_allowance' => $this->monthly_credit_allowance,
            'credits_balance' => $this->credits_balance,
            'current_plan' => $this->subscriptionPlan
                && (
                    $this->subscriptionPlan->is_internal
                    || ($this->subscriptionPlan->is_public && $this->subscriptionPlan->is_active)
                )
                    ? (new CurrentSubscriptionPlanResource($this->subscriptionPlan))->resolve($request)
                    : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
