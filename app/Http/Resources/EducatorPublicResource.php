<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An educator profile as a centre (or the public, if allowed) sees it.
 * Contact details and documents are never included.
 *
 * @mixin \App\Models\EducatorProfile
 */
class EducatorPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);

        return [
            'slug' => $this->slug,
            'full_name' => $this->fullName(),
            'initials' => Format::initials($this->fullName()),
            'photo_url' => $media->url($this->photo_path),
            'professional_title' => $this->professional_title,
            'website' => $this->website,
            'city' => $this->city,
            'province' => $this->province,
            'years_experience' => $this->years_experience,
            'languages' => $this->languages ?? [],
            'is_verified' => (bool) $this->is_verified,
            'open_to_work' => (bool) $this->open_to_work,
            'professional_summary' => $this->professional_summary,
            'teaching_philosophy' => $this->teaching_philosophy,
            'introduction_video_url' => $media->url($this->introduction_video_path),
            'age_groups_preferred' => $this->age_groups_preferred ?? [],
            'areas_of_expertise' => $this->areas_of_expertise ?? [],
            'special_interests' => $this->special_interests ?? [],
            'professional_development' => Format::lines($this->professional_development),
            'employment_preferences' => Format::options('employment_types', $this->employment_preferences),
            'availability' => $this->availability ?? [],
            'available_from' => $this->available_from?->toDateString(),
            'criminal_record_check' => $this->show_criminal_record_status && $this->criminal_record_check_status === 'completed'
                ? 'Completed'.($this->criminal_record_checked_on ? ' '.$this->criminal_record_checked_on->format('M Y') : '')
                : null,
            'credentials' => EducatorCredentialResource::collection($this->whenLoaded('credentials')),
            'portfolio' => collect($this->portfolio ?? [])->map(fn ($item) => [
                'title' => $item['title'] ?? 'Portfolio',
                'url' => $item['url'] ?? null,
                'note' => $item['note'] ?? null,
            ])->values(),
        ];
    }
}
