<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ];
    }

    /**
     * Configure the factory to create an owner user for the company.
     */
    public function withOwner(): static
    {
        return $this->afterCreating(function (Company $company): void {
            $owner = User::factory()->create([
                'company_id' => $company->id,
                'role' => 'owner',
            ]);

            $company->forceFill(['owner_id' => $owner->id])->save();
        });
    }
}
