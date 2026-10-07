<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentreGalleryItem extends Model
{
    public const CATEGORIES = ['classrooms', 'outdoor_play', 'learning_environments', 'special_events'];

    protected $fillable = [
        'childcare_centre_id', 'type', 'file_path', 'title', 'caption', 'alt_text', 'category',
        'is_cover', 'mime_type', 'size', 'sort_order', 'is_public',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_cover' => 'boolean', 'size' => 'integer', 'sort_order' => 'integer'];
    }

    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
