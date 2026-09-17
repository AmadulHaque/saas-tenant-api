<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Concerns\AuthorizesSubscription;
use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    use AuthorizesSubscription;
    use ResolvesTenantCompany;

    /**
     * Subscribe the company to a plan (owner only).
     */
    public function __invoke(StoreSubscriptionRequest $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeSubscription('create', $company);

        $plan = SubscriptionPlan::query()->findOrFail($request->integer('plan_id'));

        $subscription = $service->subscribe($company, $plan);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ], 201);
    }
}
