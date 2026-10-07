<?php

namespace App\Http\Requests\Centre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a posting. Drafts only need a title; the publish checklist is
 * enforced by JobService when `publish` is true.
 */
class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['salary_min', 'salary_max'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/[^0-9.]/', '', $this->input($field)) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        $keys = fn (string $group) => Rule::in(array_keys(config("platform.{$group}")));
        $diffAddress = $this->input('use_centre_address') === false;

        return [
            'publish' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', $keys('job_categories')],
            'employment_type' => ['nullable', $keys('employment_types')],
            'number_of_positions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'use_centre_address' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => [$diffAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'province' => [$diffAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'postal_code' => [$diffAddress ? 'required' : 'nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'show_pay' => ['sometimes', 'boolean'],
            'salary_min' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'gte:salary_min'],
            'salary_period' => ['nullable', $keys('salary_periods')],
            'description' => ['nullable', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'required_education' => ['nullable', $keys('education_requirements')],
            'experience_level' => ['nullable', $keys('experience_levels')],
            'required_certifications' => ['nullable', 'array'],
            'required_certifications.*' => [$keys('certifications')],
            'application_instructions' => ['nullable', 'string', 'max:2000'],
            'practicum_level' => ['nullable', $keys('practicum_levels')],
            'practicum_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'partner_institutions' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'salary_max.gte' => 'The top of the pay range must be at least the bottom.',
            'application_deadline.after_or_equal' => 'The application deadline cannot be in the past.',
        ];
    }
}
