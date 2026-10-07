<?php

namespace App\Http\Resources;

use App\Services\CentreProfileService;
use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;

/**
 * The centre as its owner edits it in the profile builder: public fields plus
 * private ones (licence number, contact person), raw form values and completion.
 *
 * @mixin \App\Models\ChildcareCentre
 */
class CentreOwnerResource extends CentreProfileResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);

        return array_merge(parent::toArray($request), [
            'contact_person' => $this->contact_person,
            'licence_number' => $this->licence_number,
            'status' => $this->status,
            'is_published' => (bool) $this->is_published,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'completion' => app(CentreProfileService::class)->completion($this->resource),
            'form' => [
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'website' => $this->website,
                'social_links' => $this->social_links ?? [],
                'address' => $this->address,
                'city' => $this->city,
                'postal_code' => $this->postal_code,
                'province' => $this->province,
                'country' => $this->country,
                'hours_of_operation' => $this->hours_of_operation ?? [],
                'licence_type' => $this->licence_type,
                'licence_number' => $this->licence_number,
                'about' => $this->about,
                'philosophy' => $this->philosophy,
                'mission_values' => $this->mission_values,
                'teaching_approaches' => $this->teaching_approaches ?? [],
                'daily_schedule' => $this->daily_schedule ?? [],
                'highlights' => $this->highlights ?? [],
                'welcome_video_title' => $this->welcome_video_title,
                'welcome_video_captions' => (bool) $this->welcome_video_captions,
                'virtual_tour_url' => $this->virtual_tour_url,
                'tuition_info' => $this->tuition_info,
                'tour_info' => $this->tour_info,
                'waitlist_info' => $this->waitlist_info,
                'programs' => $this->programs->map->only([
                    'id', 'name', 'description', 'min_age_months', 'max_age_months', 'schedule', 'capacity',
                    'educator_count', 'availability', 'tuition_amount', 'tuition_frequency',
                ])->values(),
                'team' => $this->teamMembers->map(fn ($member) => $member->only(['id', 'name', 'title', 'years_experience', 'credentials', 'bio'])
                    + ['photo_url' => $media->url($member->photo_path)])->values(),
                'faqs' => $this->faqs->map->only(['id', 'question', 'answer'])->values(),
            ],
            // The owner sees every gallery item, including hidden ones.
            'gallery' => $this->gallery->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'url' => $media->url($item->file_path),
                'caption' => $item->caption,
                'alt_text' => $item->alt_text,
                'category' => Format::option('gallery_categories', $item->category),
                'is_cover' => $item->is_cover,
                'is_public' => $item->is_public,
            ])->values(),
            'resources' => $this->resources->map(fn ($resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'description' => $resource->description,
                'url' => $media->url($resource->file_path),
                'mime_type' => $resource->mime_type,
            ])->values(),
        ]);
    }
}
