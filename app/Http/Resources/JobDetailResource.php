<?php

namespace App\Http\Resources;

use App\Support\Format;
use Illuminate\Http\Request;

/**
 * Full job posting, used by the job detail pane and the centre's edit form.
 *
 * @mixin \App\Models\Job
 */
class JobDetailResource extends JobResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user() && (int) $request->user()->id === (int) $this->centre?->owner_user_id;

        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'responsibilities' => Format::lines($this->responsibilities),
            'benefits' => Format::lines($this->benefits),
            'required_education' => Format::option('education_requirements', $this->required_education),
            'experience_level' => Format::option('experience_levels', $this->experience_level),
            'required_certifications' => Format::options('certifications', $this->required_certifications),
            'application_instructions' => $this->application_instructions,
            'practicum_hours' => $this->practicum_hours,
            'partner_institutions' => $this->partner_institutions,
            'accepting_applications' => $this->isAcceptingApplications(),
            'centre' => $this->whenLoaded('centre', fn () => array_merge($this->centreSummary(), [
                'has_welcome_video' => (bool) $this->centre->welcome_video_path,
                'is_public' => $this->centre->isPubliclyVisible(),
            ])),
            // Raw values for the centre's edit form.
            'form' => $this->when($isOwner, fn () => [
                'title' => $this->title,
                'category' => $this->category,
                'employment_type' => $this->employment_type,
                'number_of_positions' => $this->number_of_positions,
                'start_date' => $this->start_date?->toDateString(),
                'application_deadline' => $this->application_deadline?->toDateString(),
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'salary_min' => $this->salary_min,
                'salary_max' => $this->salary_max,
                'salary_period' => $this->salary_period,
                'show_pay' => $this->show_pay,
                'description' => $this->description,
                'responsibilities' => $this->responsibilities,
                'benefits' => $this->benefits,
                'required_education' => $this->required_education,
                'experience_level' => $this->experience_level,
                'required_certifications' => $this->required_certifications ?? [],
                'application_instructions' => $this->application_instructions,
                'practicum_level' => $this->practicum_level,
                'practicum_hours' => $this->practicum_hours,
                'partner_institutions' => $this->partner_institutions,
            ]),
        ]);
    }
}
