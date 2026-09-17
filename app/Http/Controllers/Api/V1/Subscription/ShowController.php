<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Concerns\AuthorizesSubscription;
use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    use AuthorizesSubscription;
    use ResolvesTenantCompany;

    /**
     * Show the company's current subscription (owner only).
     */
    public function __invoke(Request $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeSubscription('view', $company);

        $subscription = $service->currentSubscription($company);

        return response()->json([
            'subscription' => $subscription === null
                ? null
                : new SubscriptionResource($subscription->load('plan')),
        ]);
    }
}
