<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PracticumOpportunity extends Model {
    protected $fillable = ['childcare_centre_id','title','description','partner_institutions','programs_accepted','available_placements','intake_start_date','intake_end_date','status'];
    protected function casts(): array { return ['partner_institutions' => 'array','programs_accepted' => 'array','intake_start_date' => 'date','intake_end_date' => 'date']; }
    public function centre() { return $this->belongsTo(ChildcareCentre::class, 'childcare_centre_id'); }
}
