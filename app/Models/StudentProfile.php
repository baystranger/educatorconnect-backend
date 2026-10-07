<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $fillable = [
        'educator_profile_id', 'enabled', 'college_university', 'program', 'expected_graduation_date',
        'practicum_level', 'practicum_start_date', 'practicum_end_date', 'required_practicum_hours',
        'preferred_location', 'looking_for_practicum',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'looking_for_practicum' => 'boolean',
            'expected_graduation_date' => 'date', 'practicum_start_date' => 'date', 'practicum_end_date' => 'date',
            'required_practicum_hours' => 'integer',
        ];
    }

    public function profile() { return $this->belongsTo(EducatorProfile::class, 'educator_profile_id'); }
}
