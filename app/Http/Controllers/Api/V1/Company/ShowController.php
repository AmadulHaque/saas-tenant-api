<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * Return the authenticated user's company.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $company->loadMissing('owner');

        $this->authorize('view', $company);

        return response()->json([
            'company' => new CompanyResource($company),
        ]);
    }
}
