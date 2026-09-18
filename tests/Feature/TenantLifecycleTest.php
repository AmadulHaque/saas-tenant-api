<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('full tenant lifecycle from registration to cancellation', function (): void {
    SubscriptionPlan::factory()->create([
        'name' => 'Free', 'slug' => 'free', 'price_cents' => 0,
        'limits' => ['max_users' => 3, 'max_customers' => 25],
    ]);
    SubscriptionPlan::factory()->create([
        'name' => 'Pro', 'slug' => 'pro', 'price_cents' => 9900,
        'limits' => ['max_users' => 50, 'max_customers' => 5000],
    ]);

    // 1. Register a fresh tenant over the API.
    $register = $this->postJson('/api/v1/auth/register', [
        'name' => 'Nora Owner',
        'email' => 'nora@journey.test',
        'password' => 'a-secure-password',
        'company_name' => 'Journey Co',
    ]);
    $register->assertCreated();
    $token = $register->json('token');
    $companyId = $register->json('company.id');
    $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

    // 2. Free plan has max_users=3: owner fills one seat.
    $this->postJson('/api/v1/subscription', ['plan_id' => planId('free')], $auth)
        ->assertCreated()
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.plan.name', 'Free');

    // 3. Two more users succeed; the fourth breaches the limit.
    foreach (['ada', 'grace'] as $name) {
        $this->postJson('/api/v1/users', [
            'name' => ucfirst($name),
            'email' => "{$name}@journey.test",
            'password' => 'a-secure-password',
            'role' => 'member',
        ], $auth)->assertCreated();
    }

    $this->postJson('/api/v1/users', [
        'name' => 'Extra Seat',
        'email' => 'extra@journey.test',
        'password' => 'a-secure-password',
        'role' => 'member',
    ], $auth)->assertUnprocessable()->assertJsonValidationErrors(['limit']);

    // 4. Customers are capped at 25 on Free — create one and record usage.
    $this->postJson('/api/v1/customers', ['name' => 'First Customer', 'email' => 'first@journey.test'], $auth)
        ->assertCreated();
    $this->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => 42], $auth)->assertCreated();

    // 5. Dashboard reflects the tenant's own data only.
    $this->getJson('/api/v1/dashboard', $auth)
        ->assertOk()
        ->assertJsonPath('dashboard.total_users', 3)
        ->assertJsonPath('dashboard.total_customers', 1)
        ->assertJsonPath('dashboard.subscription.plan.name', 'Free');

    // 6. Upgrade to Pro; the Free subscription is cancelled, not deleted.
    $proId = planId('pro');
    $this->postJson('/api/v1/subscription', ['plan_id' => $proId], $auth)->assertCreated();

    $this->postJson('/api/v1/users', [
        'name' => 'Extra Seat',
        'email' => 'extra@journey.test',
        'password' => 'a-secure-password',
        'role' => 'member',
    ], $auth)->assertCreated();

    $history = DB::table('subscriptions')
        ->where('company_id', $companyId)
        ->orderBy('id')
        ->get();
    expect($history)->toHaveCount(2)
        ->and($history[0]->status)->toBe('cancelled')
        ->and($history[1]->status)->toBe('active');

    // 7. Cancel; then the fourth seat check no longer applies.
    $this->patchJson('/api/v1/subscription', ['status' => 'cancelled'], $auth)
        ->assertOk()
        ->assertJsonPath('subscription.status', 'cancelled');

    $this->postJson('/api/v1/users', [
        'name' => 'No Limits Anymore',
        'email' => 'free@journey.test',
        'password' => 'a-secure-password',
        'role' => 'member',
    ], $auth)->assertCreated();

    // 8. Logout revokes the token.
    $this->postJson('/api/v1/auth/logout', [], $auth)->assertNoContent();

    // Reset resolved guards so the revoked token is re-validated on a fresh request.
    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/v1/me', $auth)->assertUnauthorized();

    expect(User::query()->where('company_id', $companyId)->count())->toBe(5);
});

function planId(string $slug): int
{
    return SubscriptionPlan::query()->where('slug', $slug)->firstOrFail()->id;
}
