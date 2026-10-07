<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Services\CentreDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesCentre;

    public function __invoke(Request $request, CentreDashboardService $dashboard): JsonResponse
    {
        return $this->ok($dashboard->build($this->centre($request), $request));
    }
}
