<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::in(User::ROLES)],
            'plan_key' => ['sometimes', 'required', Rule::in(User::PLAN_KEYS)],
            'is_unlimited' => ['sometimes', 'required', 'boolean'],
            'monthly_credit_allowance' => ['sometimes', 'required', 'integer', 'min:0'],
            'credits_balance' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
