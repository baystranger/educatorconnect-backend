<?php

namespace App\Http\Requests\Centre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GalleryUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('platform.uploads.image_max_kb'), 'dimensions:min_width=400,min_height=300,max_width=8000,max_height=8000'],
            'category' => ['sometimes', Rule::in(array_keys(config('platform.gallery_categories')))],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.max' => 'Each photo must be 10 MB or smaller.',
            'files.*.mimes' => 'Upload JPG, PNG or WebP photos.',
            'files.*.dimensions' => 'Photos must be at least 400 × 300 pixels.',
        ];
    }
}
