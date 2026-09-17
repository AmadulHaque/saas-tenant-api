<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => str($name)->slug()->toString(),
            'price_cents' => fake()->numberBetween(0, 50000),
            'billing_interval' => BillingInterval::Monthly,
            'limits' => [
                'max_users' => 10,
                'max_customers' => 500,
            ],
            'is_active' => true,
        ];
    }

    /**
     * Plan with no enforced limits (unlimited).
     */
    public function withoutLimits(): static
    {
        return $this->state(fn (): array => [
            'limits' => null,
        ]);
    }

    /**
     * Plan billed yearly.
     */
    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'billing_interval' => BillingInterval::Yearly,
        ]);
    }

    /**
     * Inactive (retired) plan, hidden from listings.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
