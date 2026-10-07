<?php

namespace App\Http\Requests\Educator;

use App\Models\EducatorProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial updates: each Profile page card sends only the fields it edits.
 */
class UpdateEducatorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tagList = ['sometimes', 'nullable', 'array', 'max:20'];
        $tag = ['string', 'max:80'];

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'professional_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'years_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'languages' => $tagList,
            'languages.*' => $tag,
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'professional_summary' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'teaching_philosophy' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'age_groups_preferred' => $tagList,
            'age_groups_preferred.*' => $tag,
            'areas_of_expertise' => $tagList,
            'areas_of_expertise.*' => $tag,
            'special_interests' => $tagList,
            'special_interests.*' => $tag,
            'professional_development' => $tagList,
            'professional_development.*' => ['string', 'max:150'],
            'employment_preferences' => ['sometimes', 'nullable', 'array'],
            'employment_preferences.*' => [Rule::in(array_merge(array_keys(config('platform.employment_types')), ['leadership', 'management']))],
            'availability' => ['sometimes', 'nullable', 'array', 'max:10'],
            'availability.*' => ['string', 'max:40'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'search_radius_km' => ['sometimes', 'integer', Rule::in([5, 10, 25, 50, 100])],
            'open_to_relocation' => ['sometimes', 'boolean'],
            'open_to_work' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::in([EducatorProfile::VISIBILITY_PUBLIC, EducatorProfile::VISIBILITY_VERIFIED_CENTRES, EducatorProfile::VISIBILITY_APPLIED_ONLY])],
            'first_aid_cpr' => ['sometimes', 'boolean'],
            'criminal_record_check_status' => ['sometimes', 'nullable', Rule::in(['completed', 'in_progress'])],
            'criminal_record_checked_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'show_criminal_record_status' => ['sometimes', 'boolean'],
            'portfolio' => ['sometimes', 'nullable', 'array', 'max:10'],
            'portfolio.*.title' => ['required', 'string', 'max:120'],
            'portfolio.*.url' => ['required', 'url:https,http', 'max:500'],
            'portfolio.*.note' => ['nullable', 'string', 'max:120'],
            'student' => ['sometimes', 'array'],
            'student.enabled' => ['sometimes', 'boolean'],
            'student.looking_for_practicum' => ['sometimes', 'boolean'],
            'student.college_university' => ['sometimes', 'nullable', 'string', 'max:150'],
            'student.program' => ['sometimes', 'nullable', 'string', 'max:150'],
            'student.expected_graduation_date' => ['sometimes', 'nullable', 'date'],
            'student.practicum_level' => ['sometimes', 'nullable', Rule::in(array_keys(config('platform.practicum_levels')))],
            'student.practicum_start_date' => ['sometimes', 'nullable', 'date'],
            'student.practicum_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:student.practicum_start_date'],
            'student.required_practicum_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2000'],
            'student.preferred_location' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
