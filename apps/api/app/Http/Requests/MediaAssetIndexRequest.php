<?php

namespace App\Http\Requests;

use App\Models\MediaAsset;
use App\Services\Media\MediaFileInspector;
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
            'project_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(MediaFileInspector::TYPES)],
            'usage_role' => ['nullable', 'string', Rule::in(MediaAsset::USAGE_ROLES)],
        ];
    }
}