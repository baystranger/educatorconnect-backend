<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticumApplication extends Model
{
    protected $fillable = ['practicum_opportunity_id', 'educator_id', 'status', 'message', 'applied_at'];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function opportunity() { return $this->belongsTo(PracticumOpportunity::class, 'practicum_opportunity_id'); }
}
