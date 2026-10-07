<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\CentrePresenter;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Centre card used in childcare search results and the homepage.
 * Expects `programs` loaded and `gallery_count` counted.
 *
 * @mixin \App\Models\ChildcareCentre
 */
class CentreCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'initials' => Format::initials($this->name),
            'licence_type' => Format::option('licence_types', $this->licence_type),
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_km' => Format::distance($this->distance ?? null),
            'is_verified' => (bool) $this->is_verified,
            'age_range' => CentrePresenter::ageRange($this->resource),
            'availability' => CentrePresenter::availability($this->resource),
            'tags' => array_slice(CentrePresenter::tags($this->resource), 0, 3),
            'summary' => $this->about ? Str::limit(Str::before($this->about, "\n"), 140) : null,
            'hours' => CentrePresenter::hoursSummary($this->resource),
            'photo_count' => (int) ($this->gallery_count ?? 0),
            'has_welcome_video' => (bool) $this->welcome_video_path,
            'cover_url' => $media->url(CentrePresenter::coverPath($this->resource)),
            'logo_url' => $media->url($this->logo_path),
        ];
    }
}
