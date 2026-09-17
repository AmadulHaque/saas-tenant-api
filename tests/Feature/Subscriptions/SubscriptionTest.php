<?php

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\QueryException;

function subPlan(array $overrides = []): SubscriptionPlan
{
    return SubscriptionPlan::factory()->create($overrides);
}

function subscriber(): array
{
    $company = Company::factory()->withOwner()->create();
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $member = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

    return ['company' => $company, 'owner' => $company->owner, 'admin' => $admin, 'member' => $member];
}

test('active plans are listed and visible to any authenticated user', function () {
    $free = subPlan(['name' => 'Alpha Free', 'price_cents' => 0]);
    $pro = subPlan(['name' => 'Zeta Pro', 'price_cents' => 9900]);
    subPlan(['name' => 'Hidden Plan', 'is_active' => false]);

    $company = Company::factory()->withOwner()->create();

    $response = $this->actingAs($company->owner, 'api')
        ->getJson('/api/v1/plans')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('id'))
        ->toContain($free->id, $pro->id)
        ->not->toBeNull();
});

test('plan detail hides inactive plans', function () {
    $active = subPlan();
    $inactive = subPlan(['is_active' => false]);

    $company = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->getJson("/api/v1/plans/{$active->id}")
        ->assertOk()
        ->assertJsonPath('plan.id', $active->id);

    $this->actingAs($company->owner, 'api')
        ->getJson("/api/v1/plans/{$inactive->id}")
        ->assertNotFound();
});

test('only the owner can view the current subscription', function () {
    ['company' => $company, 'owner' => $owner, 'admin' => $admin, 'member' => $member] = subscriber();
    $plan = subPlan();

    Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/subscription')
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.plan.id', $plan->id);

    foreach ([$admin, $member] as $user) {
        $this->actingAs($user, 'api')
            ->getJson('/api/v1/subscription')
            ->assertForbidden();
    }
});

test('companies without a subscription get a null payload', function () {
    $company = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->getJson('/api/v1/subscription')
        ->assertOk()
        ->assertJsonPath('subscription', null);
});

test('owner can subscribe and changing plans cancels the old subscription', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();
    $starter = subPlan(['name' => 'Starter A', 'billing_interval' => 'monthly']);
    $pro = subPlan(['name' => 'Pro A', 'price_cents' => 9900]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $starter->id])
        ->assertCreated()
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.plan.id', $starter->id);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $pro->id])
        ->assertCreated()
        ->assertJsonPath('subscription.plan.id', $pro->id);

    expect($company->subscriptions()->count())->toBe(2)
        ->and($company->activeSubscription->plan_id)->toBe($pro->id)
        ->and($company->subscriptions()->where('plan_id', $starter->id)->first()->status->value)->toBe('cancelled');
});

test('subscribing to the same active plan is rejected', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();
    $plan = subPlan();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

test('non-owners cannot manage subscriptions', function () {
    ['company' => $company, 'admin' => $admin, 'member' => $member] = subscriber();
    $plan = subPlan();

    foreach ([$admin, $member] as $user) {
        $this->actingAs($user, 'api')
            ->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
            ->assertForbidden();

        $this->actingAs($user, 'api')
            ->patchJson('/api/v1/subscription', ['status' => 'cancelled'])
            ->assertForbidden();
    }

    expect($company->activeSubscription)->toBeNull();
});

test('inactive or unknown plans are rejected', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();
    $inactive = subPlan(['is_active' => false]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $inactive->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

test('owner can cancel and later resubscribe', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();
    $plan = subPlan();

    $this->actingAs($owner, 'api')->postJson('/api/v1/subscription', ['plan_id' => $plan->id])->assertCreated();

    $this->actingAs($owner, 'api')
        ->patchJson('/api/v1/subscription', ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('subscription.status', 'cancelled');

    expect($company->activeSubscription)->toBeNull();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
        ->assertCreated()
        ->assertJsonPath('subscription.status', 'active');

    expect($company->subscriptions()->where('status', 'cancelled')->count())->toBe(1);
});

test('cancelling without an active subscription returns 404', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();

    $this->actingAs($owner, 'api')
        ->patchJson('/api/v1/subscription', ['status' => 'cancelled'])
        ->assertNotFound();
});

test('past-due subscriptions are lazily expired on read', function () {
    ['company' => $company, 'owner' => $owner] = subscriber();
    $plan = subPlan();

    $subscription = Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/subscription')
        ->assertOk()
        ->assertJsonPath('subscription', null);

    expect($subscription->fresh()->status->value)->toBe('expired');
});

test('database still blocks a second active subscription', function () {
    ['company' => $company] = subscriber();
    $plan = subPlan();

    Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    expect(fn () => Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]))->toThrow(QueryException::class);
});
