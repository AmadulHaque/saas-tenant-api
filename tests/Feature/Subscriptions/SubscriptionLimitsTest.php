<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

function limitedTenant(int $maxUsers, int $maxCustomers): array
{
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Limited '.uniqid(),
        'limits' => ['max_users' => $maxUsers, 'max_customers' => $maxCustomers],
    ]);

    $company = Company::factory()->withOwner()->create();

    Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return ['company' => $company, 'owner' => $company->owner, 'plan' => $plan];
}

test('user creation is capped by the plan max_users limit', function () {
    ['company' => $company, 'owner' => $owner] = limitedTenant(2, 10);

    // Owner occupies one seat; one more allowed.
    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Second Seat',
            'email' => 'second@seat.test',
            'password' => 'a-secure-password',
            'role' => 'member',
        ])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Third Seat',
            'email' => 'third@seat.test',
            'password' => 'a-secure-password',
            'role' => 'member',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});

test('customer creation is capped by the plan max_customers limit', function () {
    ['company' => $company, 'owner' => $owner] = limitedTenant(10, 1);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'First', 'email' => 'first@limit.test'])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'Second', 'email' => 'second@limit.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});

test('soft-deleted records free their seats', function () {
    ['company' => $company, 'owner' => $owner] = limitedTenant(2, 1);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $customer->delete();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'Freed Seat', 'email' => 'freed@seat.test'])
        ->assertCreated();

    $admin->delete();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Replacement',
            'email' => 'replacement@seat.test',
            'password' => 'a-secure-password',
            'role' => 'admin',
        ])
        ->assertCreated();
});

test('null limits mean unlimited', function () {
    $plan = SubscriptionPlan::factory()->withoutLimits()->create();
    $company = Company::factory()->withOwner()->create();

    Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    for ($i = 1; $i <= 6; $i++) {
        $this->actingAs($company->owner, 'api')
            ->postJson('/api/v1/customers', [
                'name' => "Unlimited {$i}",
                'email' => "u{$i}@unlimited.test",
            ])->assertCreated();
    }
});

test('companies without an active subscription are not limited', function () {
    $company = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'No Sub', 'email' => 'nosub@plan.test'])
        ->assertCreated();
});

test('cancelling the subscription stops future limit checks', function () {
    ['company' => $company, 'owner' => $owner] = limitedTenant(10, 1);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'One', 'email' => 'one@cancel.test'])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->patchJson('/api/v1/subscription', ['status' => 'cancelled'])
        ->assertOk();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'Two', 'email' => 'two@cancel.test'])
        ->assertCreated();
});
