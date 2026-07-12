<?php

namespace App\Http\Requests;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaAssetIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'project_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(MediaAsset::TYPES)],
            'usage_role' => ['nullable', 'string', Rule::in(MediaAsset::USAGE_ROLES)],
            'origin' => ['nullable', 'string', Rule::in(['uploaded', 'generated'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
