<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A directory listing as its owner (or an administrator) sees it: every editable
 * field plus moderation state.
 *
 * @mixin \App\Models\ProfessionalListing
 */
class ProfessionalListingOwnerResource extends JsonResource
{
    public const STATUS_LABELS = [
        'pending_review' => 'In review',
        'claimed_verified' => 'Live',
        'unclaimed' => 'Unclaimed',
        'rejected' => 'Changes needed',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'initials' => Format::initials($this->name),
            'professional_category_id' => $this->professional_category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name', 'slug'])),
            'professional_title' => $this->professional_title,
            'description' => $this->description,
            'services_offered' => $this->services_offered ?? [],
            'credentials' => $this->credentials ?? [],
            'address' => $this->address,
            'service_area' => $this->service_area,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'social_links' => $this->social_links ?? [],
            'book_consultation' => (bool) $this->book_consultation,
            'logo_url' => app(MediaStorage::class)->url($this->photo_or_logo_path),
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)),
            'is_live' => in_array($this->status, ['unclaimed', 'claimed_verified'], true),
            'is_verified' => (bool) $this->is_verified,
            'review_notes' => $this->review_notes,
            'profile_views' => (int) $this->profile_views,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name', 'email'])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
