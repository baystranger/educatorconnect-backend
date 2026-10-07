<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
class HealthController extends Controller
{
    public function __invoke() {
        DB::select('select 1');
        return response()->json(['status'=>'ok','timestamp'=>now()->toIso8601String()]);
    }
}
