<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Concerns\AuthorizesSubscription;
use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    use AuthorizesSubscription;
    use ResolvesTenantCompany;

    /**
     * Cancel the company's active subscription (owner only).
     */
    public function __invoke(UpdateSubscriptionRequest $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $this->authorizeSubscription('update', $company);

        $subscription = $service->currentSubscription($company);

        abort_if($subscription === null, 404, 'No active subscription.');

        $subscription = $service->cancel($subscription);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ]);
    }
}
