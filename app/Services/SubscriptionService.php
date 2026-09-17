<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subscription lifecycle: assignment, plan changes, cancellation, expiration.
 */
class SubscriptionService
{
    /**
     * Subscribe a company to a plan, replacing any current subscription.
     *
     * Runs inside a row lock on the company to serialize concurrent
     * subscription changes for the same tenant.
     */
    public function subscribe(Company $company, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($company, $plan): Subscription {
            Company::query()->whereKey($company->id)->lockForUpdate()->get();

            $current = $company->activeSubscription()->first();

            if ($current !== null) {
                if ($current->plan_id === $plan->id) {
                    throw ValidationException::withMessages([
                        'plan_id' => 'The company is already subscribed to this plan.',
                    ]);
                }

                $current->forceFill([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
                $current->save();
            }

            return Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'ends_at' => now()->addMonths($plan->billing_interval->months()),
            ]);
        });
    }

    /**
     * Cancel an active subscription. Historical rows are never deleted.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'Only active subscriptions can be cancelled.',
            ]);
        }

        $subscription->forceFill([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        $subscription->save();

        return $subscription;
    }

    /**
     * Mark past-due active subscriptions of the company as expired.
     *
     * Lazy expiration complements the scheduled sweep for tenants
     * that are not touched by the job.
     */
    public function expirePastDue(Company $company): int
    {
        return Subscription::query()
            ->where('company_id', $company->id)
            ->pastDue()
            ->update([
                'status' => SubscriptionStatus::Expired->value,
            ]);
    }

    /**
     * The company's active subscription, lazily expiring it first if past due.
     */
    public function currentSubscription(Company $company): ?Subscription
    {
        $this->expirePastDue($company);

        return $company->activeSubscription()->first();
    }

    /**
     * Resolve the feature limit for the company from its active plan.
     *
     * Returns null when unlimited, or when no active subscription exists.
     */
    public function featureLimit(Company $company, string $feature): ?int
    {
        $subscription = $this->currentSubscription($company);

        if ($subscription === null) {
            return null;
        }

        return $subscription->plan->limit($feature);
    }
}
