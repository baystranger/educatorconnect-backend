<?php

namespace App\Http\Requests\Centre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One step of the centre profile builder: PUT /centre/profile/{section}.
 */
class CentreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $keys = fn (string $group) => Rule::in(array_keys(config("platform.{$group}")));

        return match ($this->route('section')) {
            'basics' => [
                'name' => ['required', 'string', 'max:255'],
                'contact_person' => ['nullable', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
                'email' => ['nullable', 'email', 'max:255'],
                'website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w.-]+\.[a-z]{2,}(\/\S*)?$/i'],
                'social_links' => ['nullable', 'array', 'max:6'],
                'social_links.*' => ['string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:120'],
                'postal_code' => ['required', 'string', 'max:20'],
                'province' => ['required', 'string', 'max:120'],
                'country' => ['nullable', 'string', 'max:100'],
                'hours_of_operation' => ['nullable', 'array', 'max:10'],
                'hours_of_operation.*.label' => ['required', 'string', 'max:40'],
                'hours_of_operation.*.value' => ['required', 'string', 'max:80'],
                'licence_type' => ['nullable', $keys('licence_types')],
                'licence_number' => ['nullable', 'string', 'max:60'],
            ],
            'about' => [
                'about' => ['nullable', 'string', 'max:2000'],
                'philosophy' => ['nullable', 'string', 'max:1000'],
                'mission_values' => ['nullable', 'string', 'max:1000'],
                'teaching_approaches' => ['nullable', 'array'],
                'teaching_approaches.*' => [$keys('teaching_approaches')],
                'highlights' => ['nullable', 'array', 'max:6'],
                'highlights.*' => ['string', 'max:40'],
                'daily_schedule' => ['nullable', 'array', 'max:16'],
                'daily_schedule.*.time' => ['required', 'string', 'max:20'],
                'daily_schedule.*.activity' => ['required', 'string', 'max:120'],
            ],
            'programs' => [
                'programs' => ['present', 'array', 'max:12'],
                'programs.*.id' => ['nullable', 'integer'],
                'programs.*.name' => ['required', 'string', 'max:80'],
                'programs.*.description' => ['nullable', 'string', 'max:500'],
                'programs.*.min_age_months' => ['nullable', 'integer', 'min:0', 'max:156'],
                'programs.*.max_age_months' => ['nullable', 'integer', 'min:1', 'max:156', 'gte:programs.*.min_age_months'],
                'programs.*.schedule' => ['nullable', $keys('program_schedules')],
                'programs.*.capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
                'programs.*.educator_count' => ['nullable', 'integer', 'min:0', 'max:100'],
                'programs.*.availability' => ['required', $keys('program_availability')],
                'programs.*.tuition_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'programs.*.tuition_frequency' => ['nullable', Rule::in(['month', 'day', 'week'])],
            ],
            'media' => [
                'welcome_video_title' => ['nullable', 'string', 'max:120'],
                'welcome_video_captions' => ['sometimes', 'boolean'],
                'virtual_tour_url' => ['nullable', 'url:https,http', 'max:500'],
            ],
            'team' => [
                'team' => ['present', 'array', 'max:40'],
                'team.*.id' => ['nullable', 'integer'],
                'team.*.name' => ['required', 'string', 'max:120'],
                'team.*.title' => ['nullable', 'string', 'max:120'],
                'team.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
                'team.*.credentials' => ['nullable', 'string', 'max:120'],
                'team.*.bio' => ['nullable', 'string', 'max:600'],
            ],
            'parents' => [
                'tuition_info' => ['nullable', 'string', 'max:1000'],
                'tour_info' => ['nullable', 'string', 'max:1000'],
                'waitlist_info' => ['nullable', 'string', 'max:1000'],
                'faqs' => ['sometimes', 'array', 'max:30'],
                'faqs.*.id' => ['nullable', 'integer'],
                'faqs.*.question' => ['required', 'string', 'max:200'],
                'faqs.*.answer' => ['required', 'string', 'max:1500'],
            ],
            default => abort(404),
        };
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number.',
            'website.regex' => 'Enter a valid website address.',
            'programs.*.max_age_months.gte' => 'The oldest age must be after the youngest age.',
        ];
    }
}
