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
     * The cache stores raw attribute rows (plain data — cache stores refuse
     * to unserialize objects, per Laravel 13's serializable_classes default),
     * and rows are rehydrated into models so casts still apply.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $rows = Cache::tags(['plans'])->remember('plans:all', self::CACHE_TTL, fn (): array => SubscriptionPlan::query()
            ->select(['id', 'name', 'slug', 'price_cents', 'billing_interval', 'limits'])
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => $plan->getAttributes())
            ->all());

        return PlanResource::collection($this->hydrate($rows));
    }

    /**
     * Rebuild unsaved, read-only models from cached attribute rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function hydrate(array $rows): EloquentCollection
    {
        return new EloquentCollection(array_map(
            fn (array $row): SubscriptionPlan => (new SubscriptionPlan)->setRawAttributes($row, true),
            $rows,
        ));
    }
}
