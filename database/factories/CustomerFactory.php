<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => CustomerStatus::Active,
        ];
    }

    /**
     * Inactive customer.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerStatus::Inactive,
        ]);
    }
}
