<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with plans and a demo tenant.
     */
    public function run(): void
    {
        $plans = collect([
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_cents' => 0,
                'limits' => ['max_users' => 3, 'max_customers' => 50],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_cents' => 2900,
                'limits' => ['max_users' => 10, 'max_customers' => 500],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_cents' => 9900,
                'limits' => ['max_users' => 50, 'max_customers' => 5000],
            ],
        ])->mapWithKeys(fn (array $plan): array => [
            $plan['name'] => SubscriptionPlan::create([
                ...$plan,
                'billing_interval' => BillingInterval::Monthly,
                'is_active' => true,
            ]),
        ]);

        // Demo tenant: owner@acme.test / admin@acme.test / member@acme.test (password: password)
        $company = Company::create(['name' => 'Acme Inc', 'slug' => 'acme']);

        $owner = User::forceCreate([
            'name' => 'Acme Owner',
            'email' => 'owner@acme.test',
            'password' => 'password',
            'company_id' => $company->id,
            'role' => 'owner',
        ]);

        foreach ([
            ['Acme Admin', 'admin@acme.test', 'admin'],
            ['Acme Member', 'member@acme.test', 'member'],
        ] as [$name, $email, $role]) {
            User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'company_id' => $company->id,
                'role' => $role,
            ]);
        }

        $company->forceFill(['owner_id' => $owner->id])->save();

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plans['Starter']->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
