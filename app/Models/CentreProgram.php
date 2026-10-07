<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentreProgram extends Model
{
    public const AVAILABILITY_SPACES = 'spaces';
    public const AVAILABILITY_WAITLIST = 'waitlist';
    public const AVAILABILITY_FULL = 'full';

    protected $fillable = [
        'childcare_centre_id', 'name', 'description', 'age_groups', 'min_age_months', 'max_age_months',
        'schedule', 'capacity', 'educator_count', 'availability', 'tuition_amount', 'tuition_frequency',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'age_groups' => 'array',
            'tuition_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'min_age_months' => 'integer',
            'max_age_months' => 'integer',
            'capacity' => 'integer',
            'educator_count' => 'integer',
        ];
    }

    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
