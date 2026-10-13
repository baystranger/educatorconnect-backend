<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'job_id', 'educator_id', 'status', 'interview_at', 'interview_location', 'interview_details',
        'cover_message', 'resume_path', 'shared_items', 'applied_at', 'reviewed_at', 'centre_seen_at', 'status_changed_at', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_items' => 'array',
            'applied_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'centre_seen_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'interview_at' => 'datetime',
        ];
    }

    public function job() { return $this->belongsTo(Job::class)->withTrashed(); }
    public function educator() { return $this->belongsTo(User::class, 'educator_id'); }
    public function events() { return $this->hasMany(ApplicationStatusEvent::class)->latest('id'); }
    public function messages() { return $this->morphMany(Message::class, 'conversation')->oldest('id'); }
}
