<?php

namespace App\Http\Controllers\Api\V1\Plan;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\SubscriptionPlan;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Plans', description: 'Public subscription plan catalog.')]
class ShowController extends Controller
{
    /**
     * Show an active subscription plan.
     */
    public function __invoke(int $id): JsonResponse
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
