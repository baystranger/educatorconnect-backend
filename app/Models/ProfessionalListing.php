<?php

namespace App\Models;

use App\Support\Website;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalListing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id', 'professional_category_id', 'name', 'slug', 'photo_or_logo_path', 'professional_title',
        'description', 'services_offered', 'credentials', 'address', 'service_area', 'city', 'province',
        'postal_code', 'country', 'latitude', 'longitude', 'website', 'email', 'phone', 'social_links',
        'book_consultation',
    ];

    protected function casts(): array
    {
        return [
            'services_offered' => 'array', 'credentials' => 'array', 'social_links' => 'array',
            'is_verified' => 'boolean', 'is_featured' => 'boolean', 'book_consultation' => 'boolean',
            'latitude' => 'float', 'longitude' => 'float', 'reviewed_at' => 'datetime',
        ];
    }

    protected function website(): Attribute { return Website::attribute(); }

    public function category() { return $this->belongsTo(ProfessionalCategory::class, 'professional_category_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function claims() { return $this->hasMany(ProfessionalClaim::class); }
}
