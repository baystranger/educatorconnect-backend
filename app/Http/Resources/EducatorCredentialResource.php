<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EducatorCredential
 */
class EducatorCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->expires_on && $this->expires_on->isPast();

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'issuer' => $this->issuer,
            'issued_on' => $this->issued_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'verification_status' => $this->verification_status,
            // Badge shown next to the credential: Verified · Current · Expired · Pending · Education
            'badge' => match (true) {
                $this->kind === 'education' => 'Education',
                $expired => 'Expired',
                $this->verification_status === 'verified' => 'Verified',
                $this->expires_on !== null => 'Current',
                default => 'Pending',
            },
        ];
    }
}
