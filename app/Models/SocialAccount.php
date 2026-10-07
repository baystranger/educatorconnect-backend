<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    public const PROVIDERS = ['google', 'linkedin'];

    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email'];

    public function user() { return $this->belongsTo(User::class); }
}
