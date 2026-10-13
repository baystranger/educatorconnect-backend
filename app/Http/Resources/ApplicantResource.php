<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\ApplicationStages;
use App\Support\Format;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An application as the hiring centre sees it on "Applicants".
 * Expects job.centre and educator.educatorProfile.credentials loaded.
 *
 * @mixin \App\Models\JobApplication
 */
class ApplicantResource extends JsonResource
{
    public bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $profile = $this->educator?->educatorProfile;
        $centre = $this->job?->centre;
        $media = app(MediaStorage::class);

        $distance = ($profile?->latitude && $centre?->latitude)
            ? Format::distance(Geo::distanceKm($centre->latitude, $centre->longitude, $profile->latitude, $profile->longitude))
            : null;

        $data = [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'status' => ['key' => $this->status, 'label' => ApplicationStages::label($this->status)],
            'next_status' => ($next = ApplicationStages::next($this->status)) && ! ApplicationStages::isClosed($this->status)
                ? ['key' => $next, 'label' => ApplicationStages::label($next)]
                : null,
            'is_unseen' => $this->centre_seen_at === null,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'interview' => $this->interview_at ? [
                'at' => $this->interview_at->toIso8601String(),
                'location' => $this->interview_location,
                'details' => $this->interview_details,
            ] : null,
            'educator' => [
                'id' => $this->educator_id,
                'slug' => $profile?->slug,
                'name' => $this->educator?->name,
                'initials' => Format::initials($this->educator?->name),
                'title' => $profile?->professional_title,
                'years_experience' => $profile?->years_experience,
                'distance_km' => $distance,
            ],
        ];

        if (! $this->detailed || ! $profile) {
            return $data;
        }

        $shared = $this->shared_items ?? ['video' => true, 'certifications' => true];
        $credentials = ($shared['certifications'] ?? true)
            ? $profile->credentials->where('kind', 'certification')->pluck('title')->values()->all()
            : [];
        if ($profile->show_criminal_record_status && $profile->criminal_record_check_status === 'completed') {
            $credentials[] = 'Record check';
        }

        $data['educator'] += [
            'summary' => $profile->professional_summary,
            'languages' => $profile->languages ?? [],
            'availability' => $profile->availability ?? [],
            'available_from' => $profile->available_from?->toDateString(),
            'looking_for' => Format::options('employment_types', $profile->employment_preferences),
            'introduction_video_url' => ($shared['video'] ?? true) ? $media->url($profile->introduction_video_path) : null,
            'has_resume' => (bool) ($this->resume_path ?: $profile->resume_path),
            'resume_name' => $profile->resume_original_name,
            'credentials' => $credentials,
        ];
        $data['cover_message'] = $this->cover_message;
        $data['messages'] = MessageResource::collection($this->whenLoaded('messages'));

        return $data;
    }
}
