<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'billing_interval' => [
                'sometimes',
                'required',
                'string',
                Rule::in(SubscriptionPlan::BILLING_INTERVALS),
            ],
            'monthly_credits' => ['sometimes', 'required', 'integer', 'min:0'],
            'max_projects_per_month' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_generations_per_month' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_public' => ['sometimes', 'required', 'boolean'],
            'is_featured' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'is_internal' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'limits' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }
    }
}
