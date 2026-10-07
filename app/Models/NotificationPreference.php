<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationPreference extends Model {
    protected $fillable = ['user_id','preferences'];
    protected function casts(): array { return ['preferences' => 'array']; }
    public function user() { return $this->belongsTo(User::class); }
}
