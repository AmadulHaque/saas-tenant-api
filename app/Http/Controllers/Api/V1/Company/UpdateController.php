<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Company', description: 'Tenant company profile (owner-managed updates).')]
class UpdateController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * Update the authenticated user's company (owner only).
     */
    public function __invoke(UpdateCompanyRequest $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $this->authorize('update', $company);

        $company->update($request->validated());

        return response()->json([
            'company' => new CompanyResource($company->fresh()),
        ]);
    }
}
