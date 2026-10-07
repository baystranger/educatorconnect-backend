<?php

namespace App\Http\Controllers\Api\V1\Educator;

use App\Http\Controllers\Controller;
use App\Services\EducatorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EducatorDashboardService $dashboard): JsonResponse
    {
        return $this->ok($dashboard->build($request->user(), $request));
    }
}
