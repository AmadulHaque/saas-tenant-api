<?php

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

function expirableTenant(string $endsAt): Company
{
    $company = Company::factory()->withOwner()->create();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Plan '.uniqid()]);

    Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => $endsAt,
    ]);

    return $company;
}

test('sweep expires past-due subscriptions globally and keeps live ones', function () {
    $pastDue = expirableTenant(now()->subDay()->toISOString());
    $future = expirableTenant(now()->addWeek()->toISOString());
    $openEnded = expirableTenant(now()->addMonths(2)->toISOString());

    $expired = app(SubscriptionService::class)->expireAllPastDue();

    expect($expired)->toBe(1)
        ->and($pastDue->activeSubscription()->exists())->toBeFalse()
        ->and($pastDue->subscriptions()->first()->status->value)->toBe('expired')
        ->and($future->activeSubscription()->exists())->toBeTrue()
        ->and($openEnded->activeSubscription()->exists())->toBeTrue();
});

test('command reports the number of expired subscriptions', function () {
    expirableTenant(now()->subHour()->toISOString());
    expirableTenant(now()->subMinute()->toISOString());

    $this->artisan('subscriptions:expire')
        ->expectsOutputToContain('Expired 2 past-due subscription(s).')
        ->assertSuccessful();

    expect(Subscription::query()->where('status', 'expired')->count())->toBe(2)
        ->and(Subscription::query()->where('status', 'active')->count())->toBe(0);
});

test('command with nothing to do succeeds with zero', function () {
    $this->artisan('subscriptions:expire')
        ->expectsOutputToContain('Expired 0 past-due subscription(s).')
        ->assertSuccessful();
});

test('sweep flushes affected tenants dashboard cache only', function () {
    $affected = expirableTenant(now()->subHour()->toISOString());
    $untouched = expirableTenant(now()->addWeek()->toISOString());

    foreach ([$affected, $untouched] as $company) {
        Cache::tags(['dashboard', "tenant:{$company->id}"])
            ->put("tenant:{$company->id}:dashboard", ['stale' => true], 300);
    }

    app(SubscriptionService::class)->expireAllPastDue();

    expect(Cache::tags(['dashboard', "tenant:{$affected->id}"])->get("tenant:{$affected->id}:dashboard"))->toBeNull()
        ->and(Cache::tags(['dashboard', "tenant:{$untouched->id}"])->get("tenant:{$untouched->id}:dashboard"))->not->toBeNull();
});

test('expiration sweep is scheduled hourly', function () {
    $events = collect(Schedule::events())
        ->map(fn ($event) => $event->command ?? '')
        ->implode(' ');

    expect($events)->toContain('subscriptions:expire');
});
