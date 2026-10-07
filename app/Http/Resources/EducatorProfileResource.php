<?php

namespace App\Http\Resources;

use App\Services\EducatorProfileService;
use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The educator's own profile, as edited on the Profile page.
 *
 * @mixin \App\Models\EducatorProfile
 */
class EducatorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);
        $student = $this->studentProfile;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'initials' => Format::initials($this->fullName()),
            'professional_title' => $this->professional_title,
            'years_experience' => $this->years_experience,
            'languages' => $this->languages ?? [],
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'photo_url' => $media->url($this->photo_path),
            'professional_summary' => $this->professional_summary,
            'teaching_philosophy' => $this->teaching_philosophy,
            'age_groups_preferred' => $this->age_groups_preferred ?? [],
            'areas_of_expertise' => $this->areas_of_expertise ?? [],
            'special_interests' => $this->special_interests ?? [],
            'professional_development' => Format::lines($this->professional_development),
            'employment_preferences' => $this->employment_preferences ?? [],
            'availability' => $this->availability ?? [],
            'available_from' => $this->available_from?->toDateString(),
            'search_radius_km' => $this->search_radius_km,
            'open_to_relocation' => (bool) $this->open_to_relocation,
            'open_to_work' => (bool) $this->open_to_work,
            'visibility' => $this->visibility,
            'is_verified' => (bool) $this->is_verified,
            'criminal_record_check_status' => $this->criminal_record_check_status,
            'criminal_record_checked_on' => $this->criminal_record_checked_on?->toDateString(),
            'show_criminal_record_status' => (bool) $this->show_criminal_record_status,
            'introduction_video_url' => $media->url($this->introduction_video_path),
            'resume' => $this->resume_path ? [
                'name' => $this->resume_original_name ?: 'Résumé',
                'size' => $this->resume_size,
            ] : null,
            'portfolio' => $this->portfolio ?? [],
            'credentials' => EducatorCredentialResource::collection($this->whenLoaded('credentials')),
            'student' => [
                'enabled' => (bool) $student?->enabled,
                'looking_for_practicum' => (bool) $student?->looking_for_practicum,
                'college_university' => $student?->college_university,
                'program' => $student?->program,
                'expected_graduation_date' => $student?->expected_graduation_date?->toDateString(),
                'practicum_level' => $student?->practicum_level,
                'practicum_start_date' => $student?->practicum_start_date?->toDateString(),
                'practicum_end_date' => $student?->practicum_end_date?->toDateString(),
                'required_practicum_hours' => $student?->required_practicum_hours,
                'preferred_location' => $student?->preferred_location,
            ],
            'setup_dismissed' => (bool) $this->setup_dismissed_at,
            'completion' => app(EducatorProfileService::class)->completion($this->resource),
        ];
    }
}
