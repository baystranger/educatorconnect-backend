<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\CentrePresenter;
use App\Support\Format;
use Illuminate\Http\Request;

/**
 * Public centre profile (parent view). Never includes private fields such as the licence number.
 *
 * @mixin \App\Models\ChildcareCentre
 */
class CentreProfileResource extends CentreCardResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);
        $publicGallery = $this->relationLoaded('gallery') ? $this->gallery->where('is_public', true)->values() : collect();

        return array_merge(parent::toArray($request), [
            'about' => $this->about,
            'philosophy' => $this->philosophy,
            'mission_values' => $this->mission_values,
            'teaching_approaches' => Format::options('teaching_approaches', $this->teaching_approaches),
            'highlights' => $this->highlights ?? [],
            'daily_schedule' => $this->daily_schedule ?? [],
            'hours_of_operation' => $this->hours_of_operation ?? [],
            'hours_full' => CentrePresenter::hoursSummary($this->resource, false),
            'availability_detail' => CentrePresenter::availabilityDetail($this->resource),
            'tuition_range' => CentrePresenter::tuitionRange($this->resource),
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'social_links' => $this->social_links ?? [],
            'welcome_video' => $this->welcome_video_path ? [
                'url' => $media->url($this->welcome_video_path),
                'title' => $this->welcome_video_title,
                'captions' => (bool) $this->welcome_video_captions,
            ] : null,
            'virtual_tour_url' => $this->virtual_tour_url,
            'tuition_info' => $this->tuition_info,
            'tour_info' => $this->tour_info,
            'waitlist_info' => $this->waitlist_info,
            'photo_count' => $publicGallery->where('type', 'image')->count(),
            'programs' => $this->whenLoaded('programs', fn () => $this->programs->where('is_active', true)->values()->map(fn ($program) => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'age_range' => Format::ageRange($program->min_age_months, $program->max_age_months),
                'min_age_months' => $program->min_age_months,
                'max_age_months' => $program->max_age_months,
                'schedule' => Format::option('program_schedules', $program->schedule),
                'capacity' => $program->capacity,
                'educator_count' => $program->educator_count,
                'availability' => Format::option('program_availability', $program->availability),
                'tuition_amount' => $program->tuition_amount,
                'tuition_frequency' => $program->tuition_frequency,
            ])),
            'gallery' => $publicGallery->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'url' => $media->url($item->file_path),
                'caption' => $item->caption,
                'alt_text' => $item->alt_text ?: $item->caption,
                'category' => Format::option('gallery_categories', $item->category),
                'is_cover' => $item->is_cover,
            ])->all(),
            'team' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'initials' => Format::initials($member->name),
                'title' => $member->title,
                'years_experience' => $member->years_experience,
                'credentials' => $member->credentials,
                'bio' => $member->bio,
                'photo_url' => $media->url($member->photo_path),
            ])),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->where('is_public', true)->values()->map(fn ($faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])),
            'resources' => $this->whenLoaded('resources', fn () => $this->resources->where('is_public', true)->values()->map(fn ($resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'description' => $resource->description,
                'url' => $media->url($resource->file_path),
                'mime_type' => $resource->mime_type,
            ])),
            'open_roles_count' => (int) ($this->open_roles_count ?? 0),
        ]);
    }
}
