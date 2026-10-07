<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message in a conversation thread. The conversation is either a job application
 * (centre <-> educator) or a parent request (centre <-> parent).
 */
class Message extends Model
{
    public const ROLE_CENTRE = 'centre';
    public const ROLE_EDUCATOR = 'educator';
    public const ROLE_PARENT = 'parent';

    protected $fillable = ['sender_user_id', 'sender_role', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation() { return $this->morphTo(); }
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
}
