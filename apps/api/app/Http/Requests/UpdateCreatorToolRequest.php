<?php

namespace App\Http\Requests;

use App\Models\CreatorTool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCreatorToolRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'required', 'string'],
            'long_description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'service_tier' => ['sometimes', 'required', 'string', Rule::in(CreatorTool::SERVICE_TIERS)],
            'output_type' => ['sometimes', 'nullable', 'string', Rule::in(CreatorTool::OUTPUT_TYPES)],
            'implementation_stage' => ['sometimes', 'required', 'string', Rule::in(CreatorTool::IMPLEMENTATION_STAGES)],
            'status' => ['sometimes', 'required', 'string', Rule::in(CreatorTool::STATUSES)],
            'route' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_cost' => ['sometimes', 'required', 'integer', 'min:0'],
            'credit_cost_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'badge_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_recommended' => ['sometimes', 'required', 'boolean'],
            'is_visible' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var CreatorTool|null $tool */
                $tool = $this->route('tool');

                if (! $tool || ! array_key_exists($tool->key, CreatorTool::IMPLEMENTED_ROUTES)) {
                    return;
                }

                $route = $this->input('route', $tool->route);
                $expectedRoute = CreatorTool::IMPLEMENTED_ROUTES[$tool->key];

                if ($route !== null && $route !== $expectedRoute) {
                    $validator->errors()->add(
                        'route',
                        "The route for {$tool->key} must be {$expectedRoute} or null.",
                    );
                }
            },
        ];
    }
}
