<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSearchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Accept comma-separated employment types from query strings.
        if (is_string($this->input('employment_types'))) {
            $this->merge(['employment_types' => array_filter(explode(',', $this->input('employment_types')))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius' => ['nullable', 'integer', Rule::in([5, 10, 25, 50, 100])],
            'employment_types' => ['nullable', 'array'],
            'employment_types.*' => [Rule::in(array_keys(config('platform.employment_types')))],
            'category' => ['nullable', Rule::in(array_keys(config('platform.job_categories')))],
            'posted_within' => ['nullable', 'integer', Rule::in([1, 7, 30])],
            'practicum_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['closest', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
