<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CentreSearchRequest extends FormRequest
{
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
            'age_group' => ['nullable', Rule::in(array_keys(config('platform.age_groups')))],
            'licence_type' => ['nullable', Rule::in(array_keys(config('platform.licence_types')))],
            'schedule' => ['nullable', Rule::in(array_keys(config('platform.program_schedules')))],
            'spaces_available' => ['nullable', 'boolean'],
            'accepting_waitlist' => ['nullable', 'boolean'],
            'has_video' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['closest', 'availability'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
