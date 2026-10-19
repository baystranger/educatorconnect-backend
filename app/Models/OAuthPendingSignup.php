<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthPendingSignup extends Model
{
    protected $table = 'oauth_pending_signups';

    protected $fillable = [
        'token_hash', 'provider', 'provider_user_id', 'email', 'name',
        'first_name', 'last_name', 'role', 'user_id', 'expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return ! $this->consumed_at && $this->expires_at->isFuture();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
