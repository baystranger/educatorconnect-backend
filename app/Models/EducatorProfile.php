<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducatorProfile extends Model
{
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_VERIFIED_CENTRES = 'verified_centres';
    public const VISIBILITY_APPLIED_ONLY = 'applied_only';

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'slug', 'professional_title', 'photo_path',
        'professional_summary', 'teaching_philosophy', 'years_experience', 'languages',
        'certifications', 'education', 'age_groups_preferred', 'areas_of_expertise',
        'special_interests', 'professional_development', 'first_aid_cpr',
        'criminal_record_check_status', 'criminal_record_checked_on', 'show_criminal_record_status',
        'resume_path', 'resume_original_name', 'resume_size', 'portfolio', 'introduction_video_path',
        'availability', 'available_from', 'search_radius_km', 'employment_preferences', 'open_to_relocation',
        'city', 'province', 'postal_code', 'country', 'latitude', 'longitude',
        'open_to_work', 'visibility', 'setup_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array', 'certifications' => 'array', 'education' => 'array',
            'age_groups_preferred' => 'array', 'areas_of_expertise' => 'array', 'special_interests' => 'array',
            'portfolio' => 'array', 'availability' => 'array', 'employment_preferences' => 'array',
            'first_aid_cpr' => 'boolean', 'open_to_relocation' => 'boolean', 'is_verified' => 'boolean',
            'is_published' => 'boolean', 'open_to_work' => 'boolean', 'show_criminal_record_status' => 'boolean',
            'years_experience' => 'integer', 'search_radius_km' => 'integer', 'resume_size' => 'integer',
            'latitude' => 'float', 'longitude' => 'float',
            'available_from' => 'date', 'criminal_record_checked_on' => 'date', 'setup_dismissed_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function studentProfile() { return $this->hasOne(StudentProfile::class); }
    public function credentials() { return $this->hasMany(EducatorCredential::class)->orderBy('sort_order')->orderBy('id'); }
    public function address() { return $this->morphMany(Address::class, 'addressable'); }
    public function viewEvents() { return $this->morphMany(ProfileViewEvent::class, 'viewable'); }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
