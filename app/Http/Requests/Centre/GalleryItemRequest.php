<?php

namespace App\Http\Requests\Centre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['sometimes', 'nullable', 'string', 'max:150'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:200'],
            'category' => ['sometimes', Rule::in(array_keys(config('platform.gallery_categories')))],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
