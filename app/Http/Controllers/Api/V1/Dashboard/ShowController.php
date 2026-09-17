<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * Show analytics for the authenticated user's company.
     */
    public function __invoke(Request $request, DashboardService $service): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'dashboard' => $service->for($company),
        ]);
    }
}
