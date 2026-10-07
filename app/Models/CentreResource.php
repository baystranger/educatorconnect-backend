<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CentreResource extends Model {
    protected $fillable = ['childcare_centre_id','title','description','file_path','mime_type','is_public'];
    protected function casts(): array { return ['is_public' => 'boolean']; }
    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
