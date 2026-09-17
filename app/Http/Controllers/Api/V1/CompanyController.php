<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Return the authenticated user's company.
     */
    public function show(Request $request): JsonResponse
    {
        $company = $request->user('api')->company;

        abort_if($company === null, 403, 'No company context.');

        $company->loadMissing('owner');

        $this->authorize('view', $company);

        return response()->json([
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Update the authenticated user's company (owner only).
     */
    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        $company = $request->user('api')->company;

        abort_if($company === null, 403, 'No company context.');

        $this->authorize('update', $company);

        $company->update($request->validated());

        return response()->json([
            'company' => new CompanyResource($company->fresh()),
        ]);
    }
}
