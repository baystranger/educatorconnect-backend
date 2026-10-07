<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentreTeamMember extends Model
{
    protected $fillable = ['childcare_centre_id', 'name', 'title', 'years_experience', 'credentials', 'bio', 'photo_path', 'sort_order'];

    protected function casts(): array
    {
        return ['years_experience' => 'integer', 'sort_order' => 'integer'];
    }

    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
