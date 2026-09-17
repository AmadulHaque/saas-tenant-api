<?php

use App\Enums\BillingInterval;
use App\Enums\CustomerStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;

test('company exposes owner, users, customers, and active subscription', function (): void {
    $company = Company::factory()->withOwner()->create();
    $plan = SubscriptionPlan::factory()->create();

    $admin = User::factory()->create([
        'company_id' => $company->id,
        'role' => UserRole::Admin,
    ]);
    Customer::factory()->count(2)->create(['company_id' => $company->id]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    expect($company->owner->isNot(null))->toBeTrue()
        ->and($company->owner->role)->toBe(UserRole::Owner)
        ->and($company->users()->count())->toBe(2)
        ->and($company->customers()->count())->toBe(2)
        ->and($company->activeSubscription->plan_id)->toBe($plan->id)
        ->and($admin->company->is($company))->toBeTrue();
});

test('subscription scopes select active and past-due rows correctly', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->create([
        'company_id' => $companyA->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->addMonth(),
    ]);

    Subscription::factory()->expired()->create([
        'company_id' => $companyA->id,
        'plan_id' => $plan->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $companyB->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->subHour(),
    ]);

    expect(Subscription::query()->active()->count())->toBe(2)
        ->and(Subscription::query()->pastDue()->count())->toBe(1);
});

test('plan limit helpers read the limits payload', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'limits' => ['max_users' => 5, 'max_customers' => null],
    ]);

    expect($plan->maxUsers())->toBe(5)
        ->and($plan->maxCustomers())->toBeNull()
        ->and(SubscriptionPlan::factory()->withoutLimits()->make()->maxUsers())->toBeNull();
});

test('billing interval resolves months', function (): void {
    expect(BillingInterval::Monthly->months())->toBe(1)
        ->and(BillingInterval::Yearly->months())->toBe(12);
});

test('role helpers classify management permissions', function (): void {
    expect(UserRole::Owner->canManage())->toBeTrue()
        ->and(UserRole::Admin->canManage())->toBeTrue()
        ->and(UserRole::Member->canManage())->toBeFalse();
});

test('customer and subscription enums cast from database values', function (): void {
    $company = Company::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    expect($customer->status)->toBeInstanceOf(CustomerStatus::class)
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($subscription->status)->toBeInstanceOf(SubscriptionStatus::class)
        ->and($subscription->starts_at)->toBeInstanceOf(Carbon::class);
});
