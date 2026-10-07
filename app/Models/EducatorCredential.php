<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducatorCredential extends Model
{
    public const KIND_CERTIFICATION = 'certification';
    public const KIND_EDUCATION = 'education';

    protected $fillable = ['kind', 'title', 'issuer', 'issued_on', 'expires_on', 'sort_order'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date'];
    }

    public function profile() { return $this->belongsTo(EducatorProfile::class, 'educator_profile_id'); }
}
