<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account. Drives the app shell (nav, avatar, role guards).
 *
 * @mixin \App\Models\User
 */
class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaStorage::class);
        $profile = $this->relationLoaded('educatorProfile') ? $this->educatorProfile : null;
        $centre = $this->relationLoaded('childcareCentres') ? $this->childcareCentres->first() : null;
        $displayName = $centre?->name ?? $this->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $displayName,
            'short_name' => $profile?->first_name ?? $this->shortCentreName($displayName),
            'initials' => Format::initials($displayName),
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'roles' => $this->getRoleNames()->values(),
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'email_verified' => $this->email_verified_at !== null,
            'has_password' => $this->password !== null,
            'social_providers' => $this->whenLoaded('socialAccounts', fn () => $this->socialAccounts->map->only(['provider', 'email'])->values()),
            'educator_profile' => $this->when($profile !== null, fn () => [
                'id' => $profile->id,
                'slug' => $profile->slug,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'city' => $profile->city,
                'province' => $profile->province,
                'postal_code' => $profile->postal_code,
                'country' => $profile->country,
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'search_radius_km' => $profile->search_radius_km,
                'professional_title' => $profile->professional_title,
                'years_experience' => $profile->years_experience,
                'has_resume' => (bool) $profile->resume_path,
                'resume_name' => $profile->resume_original_name,
                'photo_url' => $media->url($profile->photo_path),
                'is_student' => (bool) $profile->studentProfile?->enabled,
                'practicum_mode' => (bool) $profile->studentProfile?->looking_for_practicum,
            ]),
            'childcare_centres' => $this->whenLoaded('childcareCentres', fn () => $this->childcareCentres->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'city' => $c->city,
                'province' => $c->province,
                'postal_code' => $c->postal_code,
                'country' => $c->country,
                'address' => $c->address,
                'status' => $c->status,
                'is_published' => (bool) $c->is_published,
                'is_verified' => (bool) $c->is_verified,
                'logo_url' => $media->url($c->logo_path),
            ])->values()),
        ];
    }

    /** "Little Sprouts Daycare" → "Little Sprouts" for the compact nav label. */
    private function shortCentreName(string $name): string
    {
        $short = preg_replace('/\s+(daycare|childcare|child care|centre|center|early learning|preschool|school)$/i', '', trim($name));

        return $short ?: $name;
    }
}
