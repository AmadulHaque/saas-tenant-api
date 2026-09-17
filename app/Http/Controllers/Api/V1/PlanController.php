<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class PlanController extends Controller
{
    /**
     * Plans cache TTL in seconds.
     */
    public const CACHE_TTL = 3600;

    /**
     * List active subscription plans.
     *
     * Served from the shared plans:all cache; invalidated by plan changes.
     */
    public function index(): AnonymousResourceCollection
    {
        $plans = Cache::tags(['plans'])->remember('plans:all', self::CACHE_TTL, function (): EloquentCollection {
            return SubscriptionPlan::query()
                ->select(['id', 'name', 'slug', 'price_cents', 'billing_interval', 'limits'])
                ->where('is_active', true)
                ->orderBy('price_cents')
                ->get();
        });

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
