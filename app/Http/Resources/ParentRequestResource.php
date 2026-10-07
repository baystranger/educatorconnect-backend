<?php

namespace App\Http\Resources;

use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A family's inquiry, tour request or waitlist sign-up, as the centre sees it.
 *
 * @mixin \App\Models\ParentRequest
 */
class ParentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'parent_name' => $this->parent_name,
            'initials' => Format::initials($this->parent_name),
            'email' => $this->email,
            'phone' => $this->phone,
            'child_age' => $this->child_age,
            'program_of_interest' => $this->program_of_interest,
            'desired_start' => $this->desired_start,
            'schedule' => $this->schedule,
            'preferred_contact_method' => Format::option('contact_methods', $this->preferred_contact_method),
            'message' => $this->message,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'has_consent' => $this->consent_at !== null,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
