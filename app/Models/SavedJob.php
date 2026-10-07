<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedJob extends Model
{
    protected $fillable = ['job_id','educator_id'];
    public function job() { return $this->belongsTo(Job::class); }
    public function educator() { return $this->belongsTo(User::class, 'educator_id'); }
}
