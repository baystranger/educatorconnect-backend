<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Job card shape used by search results, recommendations and dashboards.
 *
 * @mixin \App\Models\Job
 */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => Format::option('job_categories', $this->category),
            'employment_type' => Format::option('employment_types', $this->employment_type),
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'pay' => $this->show_pay ? Format::pay($this->salary_min, $this->salary_max, $this->salary_period) : null,
            'number_of_positions' => $this->number_of_positions,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'application_deadline' => $this->application_deadline?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'distance_km' => Format::distance($this->distance ?? null),
            'practicum_level' => Format::option('practicum_levels', $this->practicum_level),
            'is_saved' => isset($this->is_saved) ? (bool) $this->is_saved : null,
            'has_applied' => isset($this->has_applied) ? (bool) $this->has_applied : null,
            'centre' => $this->whenLoaded('centre', fn () => $this->centreSummary()),
        ];
    }

    protected function centreSummary(): array
    {
        return [
            'id' => $this->centre->id,
            'name' => $this->centre->name,
            'slug' => $this->centre->slug,
            'city' => $this->centre->city,
            'initials' => Format::initials($this->centre->name),
            'is_verified' => (bool) $this->centre->is_verified,
            'logo_url' => app(MediaStorage::class)->url($this->centre->logo_path),
        ];
    }
}
