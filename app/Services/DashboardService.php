<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds and caches company-level dashboard analytics.
 *
 * Cache strategy (see docs/caching.md):
 * - Key: tenant:{companyId}:dashboard, tags: dashboard + tenant:{companyId}.
 * - TTL: 300 seconds.
 * - Scope: tenant-isolated via the tenant:{companyId} tag.
 * - Invalidation: FlushTenantDashboardCache observer on user, customer,
 *   subscription, and usage-record writes.
 * - Fallback: compute fresh when the cache store is unavailable.
 */
class DashboardService
{
    /**
     * Dashboard cache TTL in seconds.
     */
    public const TTL = 300;

    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * Return cached analytics for the company, computing on miss.
     *
     * @return array<string, mixed>
     */
    public function for(Company $company): array
    {
        $key = "tenant:{$company->id}:dashboard";

        try {
            return Cache::tags(['dashboard', "tenant:{$company->id}"])
                ->remember($key, self::TTL, fn (): array => $this->build($company));
        } catch (Throwable) {
            return $this->build($company);
        }
    }

    /**
     * Compute the dashboard payload.
     *
     * @return array<string, mixed>
     */
    private function build(Company $company): array
    {
        $subscription = $this->subscriptions->currentSubscription($company);

        return [
            'total_users' => User::query()->where('company_id', $company->id)->count(),
            'total_customers' => Customer::query()->where('company_id', $company->id)->count(),
            'usage' => $this->usageSummary($company),
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status->value,
                'plan' => [
                    'name' => $subscription->plan->name,
                    'billing_interval' => $subscription->plan->billing_interval->value,
                ],
                'ends_at' => $subscription->ends_at?->toISOString(),
            ],
            'recent_users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'created_at'])
                ->where('company_id', $company->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'created_at' => $user->created_at->toISOString(),
                ])
                ->all(),
            'recent_customers' => Customer::query()
                ->select(['id', 'name', 'email', 'status', 'created_at'])
                ->where('company_id', $company->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'status' => $customer->status->value,
                    'created_at' => $customer->created_at->toISOString(),
                ])
                ->all(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Signed usage totals grouped by feature (append-only ledger sums).
     *
     * @return array{total_events: int, by_feature: array<string, int>}
     */
    private function usageSummary(Company $company): array
    {
        $sums = UsageRecord::query()
            ->select(['feature', DB::raw('SUM(delta) as total_delta')])
            ->where('company_id', $company->id)
            ->groupBy('feature')
            ->orderBy('feature')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->feature => (int) $row->total_delta]);

        return [
            'total_events' => (int) UsageRecord::query()->where('company_id', $company->id)->count(),
            'by_feature' => $sums->all(),
        ];
    }
}
