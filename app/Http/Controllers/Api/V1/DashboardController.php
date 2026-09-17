<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show analytics for the authenticated user's company.
     */
    public function show(Request $request, DashboardService $service): JsonResponse
    {
        $company = $request->user('api')->company;

        abort_if($company === null, 403, 'No company context.');

        return response()->json([
            'dashboard' => $service->for($company),
        ]);
    }
}
