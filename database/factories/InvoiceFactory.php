<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'plan_name' => fn (array $attributes): string => SubscriptionPlan::query()
                ->find($attributes['plan_id'])
                ?->name ?? 'Plan',
            'amount_cents' => fn (array $attributes): int => SubscriptionPlan::query()
                ->find($attributes['plan_id'])
                ?->price_cents ?? 0,
            'currency' => 'usd',
            'status' => InvoiceStatus::Paid,
            'gateway' => 'fake',
            'gateway_reference' => fn (): string => 'fake_'.fake()->uuid(),
            'paid_at' => fn (array $attributes): ?\Illuminate\Support\Carbon => $attributes['status'] === InvoiceStatus::Paid->value
                ? now()
                : null,
        ];
    }

    /**
     * Invoice awaiting settlement by the gateway or webhook.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Pending,
            'gateway_reference' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Invoice rejected by the gateway.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Failed,
            'paid_at' => null,
        ]);
    }
}
