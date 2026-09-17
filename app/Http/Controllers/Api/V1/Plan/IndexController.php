<?php

namespace App\Http\Controllers\Api\V1\Plan;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\SubscriptionPlan;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

#[Group('Plans', description: 'Public subscription plan catalog.')]
class IndexController extends Controller
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
    public function __invoke(): AnonymousResourceCollection
    {
        $plans = Cache::tags(['plans'])->remember('plans:all', self::CACHE_TTL, fn (): EloquentCollection => SubscriptionPlan::query()
            ->select(['id', 'name', 'slug', 'price_cents', 'billing_interval', 'limits'])
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get());

        return PlanResource::collection($plans);
    }
}
