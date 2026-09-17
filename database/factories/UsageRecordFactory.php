<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
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
            'feature' => 'api_requests',
            'delta' => fake()->numberBetween(1, 100),
            'metadata' => null,
            'recorded_at' => now(),
        ];
    }
}
