<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectFashionTryOnResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'media_asset_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
