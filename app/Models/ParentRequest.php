<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An inquiry, tour request or waitlist sign-up from a parent (parents have no account).
 */
class ParentRequest extends Model
{
    public const TYPES = ['inquiry', 'tour', 'waitlist'];
    public const STATUSES = ['new', 'replied', 'confirmed', 'waitlisted', 'declined', 'closed'];

    protected $fillable = [
        'type', 'parent_name', 'email', 'phone', 'child_age', 'program_of_interest', 'desired_start',
        'schedule', 'preferred_contact_method', 'message', 'requested_at', 'metadata', 'status',
        'responded_at', 'consent_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
    public function messages() { return $this->morphMany(Message::class, 'conversation')->oldest('id'); }
}
