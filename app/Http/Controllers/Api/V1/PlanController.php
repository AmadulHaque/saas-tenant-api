<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    /**
     * List active subscription plans.
     */
    public function index(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::query()
            ->select(['id', 'name', 'slug', 'price_cents', 'billing_interval', 'limits'])
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get();

        return PlanResource::collection($plans);
    }

    /**
     * Show an active subscription plan.
     */
    public function show(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()
            ->select(['id', 'name', 'slug', 'price_cents', 'billing_interval', 'limits'])
            ->where('is_active', true)
            ->find($id);

        abort_if($plan === null, 404);

        return response()->json([
            'plan' => new PlanResource($plan),
        ]);
    }
}
