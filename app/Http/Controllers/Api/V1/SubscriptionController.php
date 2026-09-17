<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Show the company's current subscription (owner only).
     */
    public function show(Request $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->companyFor($request);
        $this->authorizeSubscription('view', $company);

        $subscription = $service->currentSubscription($company);

        return response()->json([
            'subscription' => $subscription === null
                ? null
                : new SubscriptionResource($subscription->load('plan')),
        ]);
    }

    /**
     * Subscribe the company to a plan (owner only).
     */
    public function store(StoreSubscriptionRequest $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->companyFor($request);
        $this->authorizeSubscription('create', $company);

        $plan = SubscriptionPlan::query()->findOrFail($request->integer('plan_id'));

        $subscription = $service->subscribe($company, $plan);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ], 201);
    }

    /**
     * Cancel the company's active subscription (owner only).
     */
    public function update(UpdateSubscriptionRequest $request, SubscriptionService $service): JsonResponse
    {
        $company = $this->companyFor($request);
        $this->authorizeSubscription('update', $company);

        $subscription = $service->currentSubscription($company);

        abort_if($subscription === null, 404, 'No active subscription.');

        $subscription = $service->cancel($subscription);

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ]);
    }

    /**
     * Authorize subscription management against the actor's company.
     */
    private function authorizeSubscription(string $ability, Company $company): void
    {
        $this->authorize($ability, (new Subscription)->forceFill(['company_id' => $company->id]));
    }

    /**
     * Resolve the actor's company for subscription management.
     */
    private function companyFor(Request $request): Company
    {
        $company = $request->user('api')->company;

        abort_if($company === null, 403, 'No company context.');

        return $company;
    }
}
