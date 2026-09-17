<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->startOfDay();

        return [
            'company_id' => Company::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonth(),
        ];
    }

    /**
     * Subscription that already ended (kept as history).
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
    }

    /**
     * Cancelled subscription.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now()->subDays(3),
        ]);
    }
}
