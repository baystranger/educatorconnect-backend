<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStatusEvent extends Model
{
    protected $fillable = ['job_application_id', 'status', 'note', 'actor_user_id'];

    public function application() { return $this->belongsTo(JobApplication::class, 'job_application_id'); }
}
