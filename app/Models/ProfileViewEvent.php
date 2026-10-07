<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileViewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['viewable_type', 'viewable_id', 'viewer_user_id', 'viewed_on'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date'];
    }

    public function viewable() { return $this->morphTo(); }
}
