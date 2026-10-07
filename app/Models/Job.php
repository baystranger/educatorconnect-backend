<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A job or practicum posting. Stored in `user_jobs` because `jobs` belongs to the queue.
 */
class Job extends Model
{
    use SoftDeletes;

    protected $table = 'user_jobs';

    protected $fillable = [
        'childcare_centre_id', 'title', 'slug', 'description', 'category', 'employment_type',
        'city', 'province', 'postal_code', 'country', 'latitude', 'longitude', 'salary_min', 'salary_max',
        'salary_period', 'show_pay', 'required_education', 'required_certifications', 'required_experience',
        'experience_level', 'practicum_level', 'practicum_hours', 'partner_institutions', 'responsibilities', 'benefits', 'application_deadline', 'start_date',
        'number_of_positions', 'application_instructions', 'status', 'published_at', 'closed_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'required_certifications' => 'array',
            'show_pay' => 'boolean',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
            'number_of_positions' => 'integer',
            'practicum_hours' => 'integer',
            'views' => 'integer',
            'application_deadline' => 'date',
            'start_date' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
    public function applications() { return $this->hasMany(JobApplication::class); }
    public function savedBy() { return $this->hasMany(SavedJob::class); }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', JobStatus::PUBLISHED->value);
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === JobStatus::PUBLISHED->value
            && (! $this->application_deadline || ! $this->application_deadline->copy()->endOfDay()->isPast());
    }
}
