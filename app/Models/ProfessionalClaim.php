<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalClaim extends Model
{
    protected $fillable = [
        'professional_listing_id', 'user_id', 'status', 'business_domain_email', 'business_phone',
        'credential_information', 'supporting_document_path', 'admin_notes', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function listing() { return $this->belongsTo(ProfessionalListing::class, 'professional_listing_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
