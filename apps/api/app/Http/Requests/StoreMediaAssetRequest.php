<?php

namespace App\Http\Requests;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaAssetRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:'.(int) config('media.max_size_kb', 51200),
            ],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->getKey()),
                ),
            ],
            'type' => ['nullable', 'string', 'max:32'],
            'usage_role' => ['nullable', 'string', 'max:64', Rule::in(MediaAsset::USAGE_ROLES)],
        ];
    }
}