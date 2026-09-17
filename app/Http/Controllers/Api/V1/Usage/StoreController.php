<?php

namespace App\Http\Controllers\Api\V1\Usage;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsageRequest;
use App\Http\Resources\UsageResource;
use App\Models\UsageRecord;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * Record a signed usage delta for the company (owner/admin only).
     */
    public function __invoke(StoreUsageRequest $request, UsageService $service): JsonResponse
    {
        $this->authorize('create', UsageRecord::class);

        $company = $this->resolveCompany($request);

        $record = $service->record(
            $company,
            $request->string('feature')->toString(),
            $request->integer('delta'),
            $request->input('metadata'),
        );

        return response()->json([
            'usage' => new UsageResource($record),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
