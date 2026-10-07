<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CentreFaq extends Model {
    protected $fillable = ['childcare_centre_id','question','answer','sort_order','is_public'];
    protected function casts(): array { return ['is_public' => 'boolean']; }
    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
