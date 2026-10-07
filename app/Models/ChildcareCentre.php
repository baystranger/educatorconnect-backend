<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildcareCentre extends Model
{
    use SoftDeletes;

    /**
     * Owner-editable attributes. Moderation fields (is_verified, is_published, status,
     * licensing_status) are deliberately excluded and set explicitly by services.
     */
    protected $fillable = [
        'owner_user_id', 'name', 'slug', 'contact_person', 'email', 'phone', 'website',
        'about', 'philosophy', 'mission_values', 'daily_routine', 'daily_schedule', 'teaching_approach',
        'teaching_approaches', 'highlights', 'logo_path', 'cover_image_path', 'welcome_video_path',
        'welcome_video_title', 'welcome_video_captions', 'virtual_tour_url', 'address', 'city', 'province',
        'postal_code', 'country', 'latitude', 'longitude', 'social_links', 'hours_of_operation',
        'licence_type', 'licence_number', 'tuition_info', 'tour_info', 'waitlist_info',
    ];

    protected $hidden = ['licence_number'];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'hours_of_operation' => 'array',
            'teaching_approaches' => 'array',
            'daily_schedule' => 'array',
            'highlights' => 'array',
            'licence_number' => 'encrypted',
            'is_verified' => 'boolean',
            'is_published' => 'boolean',
            'welcome_video_captions' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'profile_views' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function programs() { return $this->hasMany(CentreProgram::class)->orderBy('sort_order')->orderBy('id'); }
    public function gallery() { return $this->hasMany(CentreGalleryItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function teamMembers() { return $this->hasMany(CentreTeamMember::class)->orderBy('sort_order')->orderBy('id'); }
    public function faqs() { return $this->hasMany(CentreFaq::class)->orderBy('sort_order')->orderBy('id'); }
    public function resources() { return $this->hasMany(CentreResource::class); }
    public function jobs() { return $this->hasMany(Job::class); }
    public function practicumOpportunities() { return $this->hasMany(PracticumOpportunity::class); }
    public function parentRequests() { return $this->hasMany(ParentRequest::class); }
    public function viewEvents() { return $this->morphMany(ProfileViewEvent::class, 'viewable'); }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('status', 'published');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_published && $this->status === 'published';
    }
}
