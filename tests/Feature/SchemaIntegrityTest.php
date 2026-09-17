<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;

test('company cannot have two active subscriptions', function (): void {
    $company = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);
})->throws(QueryException::class);

test('company can keep historical subscriptions alongside the active one', function (): void {
    $company = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->expired()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    Subscription::factory()->cancelled()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    expect($company->subscriptions()->count())->toBe(3)
        ->and($company->activeSubscription()->count())->toBe(1);
});

test('customer email is unique within a company but reusable across companies', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Customer::factory()->create([
        'company_id' => $companyA->id,
        'email' => 'shared@example.com',
    ]);

    Customer::factory()->create([
        'company_id' => $companyB->id,
        'email' => 'shared@example.com',
    ]);

    expect(fn () => Customer::factory()->create([
        'company_id' => $companyA->id,
        'email' => 'shared@example.com',
    ]))->toThrow(QueryException::class);
});

test('soft-deleted customer frees the email for reuse', function (): void {
    $company = Company::factory()->create();

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'email' => 'gone@example.com',
    ]);

    $customer->delete();

    $replacement = Customer::factory()->create([
        'company_id' => $company->id,
        'email' => 'gone@example.com',
    ]);

    expect($replacement->exists)->toBeTrue();
});

test('plan referenced by a subscription cannot be deleted', function (): void {
    $company = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    expect(fn () => $plan->delete())->toThrow(QueryException::class);
});

test('subscription period end must be after start', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Period check constraint is only enforced on PostgreSQL.');
    }

    $company = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    assertDatabaseHas('companies', ['id' => $company->id]);

    Subscription::create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->subDay(),
    ]);
})->throws(QueryException::class);
