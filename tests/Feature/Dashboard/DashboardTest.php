<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function dashTenant(): array
{
    $company = Company::factory()->withOwner()->create();
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

    return ['company' => $company, 'owner' => $company->owner, 'admin' => $admin];
}

function seedSubscription(Company $company, SubscriptionPlan $plan): Subscription
{
    return Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);
}

test('dashboard shows counts, subscription, and recent activity for all roles', function (): void {
    ['company' => $company, 'owner' => $owner, 'admin' => $admin] = dashTenant();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Dash Pro', 'billing_interval' => 'monthly']);
    seedSubscription($company, $plan);
    Customer::factory()->count(2)->create(['company_id' => $company->id]);

    foreach ([$owner, $admin] as $user) {
        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('dashboard.total_users', 2)
            ->assertJsonPath('dashboard.total_customers', 2)
            ->assertJsonPath('dashboard.subscription.plan.name', 'Dash Pro')
            ->assertJsonPath('dashboard.subscription.status', 'active');

        expect(count($response->json('dashboard.recent_users')))->toBe(2);
    }
});

test('dashboard without a subscription reports null', function (): void {
    ['company' => $company, 'owner' => $owner] = dashTenant();

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.subscription', null);
});

test('cache is hit: direct database writes stay invisible until TTL or invalidation', function (): void {
    ['company' => $company, 'owner' => $owner] = dashTenant();

    $this->actingAs($owner, 'api')->getJson('/api/v1/dashboard')->assertOk();

    // Write silently (no model events): observer can't flush, cache stays stale.
    User::factory()->createQuietly(['company_id' => $company->id]);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.total_users', 2);
});

test('api mutation invalidates the tenant cache immediately', function (): void {
    ['company' => $company, 'owner' => $owner] = dashTenant();

    $this->actingAs($owner, 'api')->getJson('/api/v1/dashboard')->assertOk();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Fresh User',
            'email' => 'fresh@dash.test',
            'password' => 'a-secure-password',
            'role' => 'member',
        ])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.total_users', 3);
});

test('cache keys are tenant isolated', function (): void {
    $a = Company::factory()->withOwner()->create();
    $b = Company::factory()->withOwner()->create();

    $this->actingAs($a->owner, 'api')->getJson('/api/v1/dashboard')->assertOk();
    $this->actingAs($b->owner, 'api')->getJson('/api/v1/dashboard')->assertOk();

    expect(Cache::tags(['dashboard', "tenant:{$a->id}"])->get("tenant:{$a->id}:dashboard"))->not->toBeNull()
        ->and(Cache::tags(['dashboard', "tenant:{$b->id}"])->get("tenant:{$a->id}:dashboard"))->toBeNull();
});

test('invalidating one tenant does not evict another tenant cache', function (): void {
    ['company' => $a, 'owner' => $ownerA] = dashTenant();
    $b = Company::factory()->withOwner()->create();

    $this->actingAs($ownerA, 'api')->getJson('/api/v1/dashboard')->assertOk();
    $this->actingAs($b->owner, 'api')->getJson('/api/v1/dashboard')->assertOk();

    // Mutate tenant B through the API; tenant A entry must survive.
    $this->actingAs($b->owner, 'api')
        ->postJson('/api/v1/customers', ['name' => 'B Customer', 'email' => 'b@iso.test'])
        ->assertCreated();

    $this->actingAs($ownerA, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.total_customers', 0);

    $this->actingAs($b->owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.total_customers', 1);
});

test('redis failure falls back to computing fresh data', function (): void {
    ['company' => $company, 'owner' => $owner] = dashTenant();
    Customer::factory()->create(['company_id' => $company->id]);

    Cache::shouldReceive('tags')->andThrow(new RuntimeException('Redis is down'));

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.total_customers', 1);
});

test('plans list is cached and refreshed after plan changes', function (): void {
    $company = Company::factory()->withOwner()->create();
    SubscriptionPlan::factory()->create(['name' => 'One']);

    $this->actingAs($company->owner, 'api')
        ->getJson('/api/v1/plans')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Warm cache entry exists for the shared plans key.
    expect(Cache::tags(['plans'])->get('plans:all'))->not->toBeNull();

    // Any plan write flushes the tag, so the next read recomputes.
    SubscriptionPlan::factory()->create(['name' => 'Two']);

    expect(Cache::tags(['plans'])->get('plans:all'))->toBeNull();

    $this->actingAs($company->owner, 'api')
        ->getJson('/api/v1/plans')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('cancelling a subscription refreshes dashboard data', function (): void {
    ['company' => $company, 'owner' => $owner] = dashTenant();
    seedSubscription($company, SubscriptionPlan::factory()->create());

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.subscription.status', 'active');

    $this->actingAs($owner, 'api')
        ->patchJson('/api/v1/subscription', ['status' => 'cancelled'])
        ->assertOk();

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('dashboard.subscription', null);
});
