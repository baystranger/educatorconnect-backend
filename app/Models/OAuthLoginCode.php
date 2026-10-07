<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthLoginCode extends Model
{
    protected $table = 'oauth_login_codes';

    protected $fillable = ['code_hash', 'user_id', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
