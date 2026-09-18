<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

function billingTenant(): array
{
    $company = Company::factory()->withOwner()->create();

    return ['company' => $company, 'owner' => $company->owner];
}

function signWebhookPayload(array $payload, string $secret): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'body' => $body,
        'signature' => hash_hmac('sha256', $body, $secret),
    ];
}

test('owner purchases a plan and the subscription activates', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 4900]);

    $response = $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id]);

    $response->assertCreated()
        ->assertJsonPath('invoice.status', 'paid')
        ->assertJsonPath('invoice.amount.cents', 4900)
        ->assertJsonPath('invoice.amount.currency', 'usd')
        ->assertJsonPath('invoice.plan.id', $plan->id)
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.plan.id', $plan->id);

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->subscription_id)->not->toBeNull()
        ->and($invoice->paid_at)->not->toBeNull()
        ->and(Subscription::query()->where('company_id', $company->id)->where('status', 'active')->count())->toBe(1);
});

test('free plans activate without a gateway charge', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 0]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertCreated()
        ->assertJsonPath('invoice.status', 'paid')
        ->assertJsonPath('invoice.gateway_reference', 'free')
        ->assertJsonPath('subscription.status', 'active');

    expect(Subscription::query()->where('company_id', $company->id)->where('status', 'active')->count())->toBe(1);
});

test('a pending charge activates later through the signed webhook', function (): void {
    Config::set('billing.webhook_secret', 'whsec-test');
    Config::set('billing.fake.behavior', 'pending');

    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 9900]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertAccepted()
        ->assertJsonPath('invoice.status', 'pending');

    expect(Subscription::query()->where('company_id', $company->id)->where('status', 'active')->count())->toBe(0);

    $invoice = Invoice::query()->sole();
    ['body' => $body, 'signature' => $signature] = signWebhookPayload([
        'invoice_id' => $invoice->id,
        'status' => 'paid',
        'reference' => 'gw_confirm_1',
    ], 'whsec-test');

    $this->postJson('/api/v1/webhooks/billing', json_decode($body, true), ['X-Signature' => $signature])
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid')
        ->assertJsonPath('invoice.gateway_reference', 'gw_confirm_1');

    $active = Subscription::query()->where('company_id', $company->id)->where('status', 'active')->first();
    expect($active)->not->toBeNull()
        ->and($active->plan_id)->toBe($plan->id);

    // Duplicate delivery is a no-op.
    $this->postJson('/api/v1/webhooks/billing', json_decode($body, true), ['X-Signature' => $signature])
        ->assertOk();

    expect(Subscription::query()->where('company_id', $company->id)->count())->toBe(1);
});

test('a declined charge records a failed invoice and activates nothing', function (): void {
    Config::set('billing.fake.behavior', 'failed');

    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 1900]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertStatus(402)
        ->assertJsonPath('invoice.status', 'failed');

    expect(Invoice::query()->sole()->status)->toBe(InvoiceStatus::Failed)
        ->and(Subscription::query()->where('company_id', $company->id)->count())->toBe(0);
});

test('purchasing the currently active plan fails validation and rolls back the invoice', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 1000]);
    app(SubscriptionService::class)->subscribe($company, $plan);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);

    expect(Invoice::query()->count())->toBe(0)
        ->and(Subscription::query()->where('company_id', $company->id)->where('status', 'active')->count())->toBe(1);
});

test('switching plans through billing keeps subscription history', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $free = SubscriptionPlan::factory()->create(['slug' => 'starter-'.fake()->uuid(), 'price_cents' => 0]);
    $pro = SubscriptionPlan::factory()->create(['price_cents' => 9900]);
    app(SubscriptionService::class)->subscribe($company, $free);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $pro->id])
        ->assertCreated()
        ->assertJsonPath('subscription.plan.id', $pro->id);

    $statuses = DB::table('subscriptions')
        ->where('company_id', $company->id)
        ->orderBy('id')
        ->pluck('status');
    expect($statuses)->toHaveCount(2)
        ->and($statuses[0])->toBe('cancelled')
        ->and($statuses[1])->toBe('active');
});

test('members may not purchase plans', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $member = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($member, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertForbidden();

    expect(Invoice::query()->count())->toBe(0);
});

test('inactive plans cannot be purchased', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

test('invoices are listed newest first and tenant-scoped', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $other = Company::factory()->withOwner()->create();
    Invoice::factory()->count(2)->create(['company_id' => $company->id]);
    Invoice::factory()->create(['company_id' => $other->id]);

    $response = $this->actingAs($owner, 'api')
        ->getJson('/api/v1/billing/invoices')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $invoice) {
        expect($invoice['company_id'] ?? null)->not->toBe($other->id);
    }

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/billing/invoices?status=paid')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/billing/invoices?status=pending')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('invoice show is owner-only and tenant-scoped', function (): void {
    ['company' => $company, 'owner' => $owner] = billingTenant();
    $invoice = Invoice::factory()->create(['company_id' => $company->id]);
    $other = Company::factory()->withOwner()->create();

    $this->actingAs($owner, 'api')
        ->getJson("/api/v1/billing/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('invoice.id', $invoice->id);

    $this->actingAs($other->owner, 'api')
        ->getJson("/api/v1/billing/invoices/{$invoice->id}")
        ->assertForbidden();

    User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $member = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);
    $this->actingAs($member, 'api')
        ->getJson("/api/v1/billing/invoices/{$invoice->id}")
        ->assertForbidden();
});

test('webhook rejects bad signatures', function (): void {
    Config::set('billing.webhook_secret', 'whsec-test');

    ['body' => $body] = signWebhookPayload(['invoice_id' => 1, 'status' => 'paid'], 'whsec-test');

    $this->postJson('/api/v1/webhooks/billing', json_decode($body, true), ['X-Signature' => 'deadbeef'])
        ->assertUnauthorized();

    $this->postJson('/api/v1/webhooks/billing', json_decode($body, true))
        ->assertUnauthorized();
});

test('webhook 404s unknown invoices and 503s when unconfigured', function (): void {
    Config::set('billing.webhook_secret', 'whsec-test');
    ['body' => $body, 'signature' => $signature] = signWebhookPayload([
        'invoice_id' => 99999,
        'status' => 'paid',
    ], 'whsec-test');

    $this->postJson('/api/v1/webhooks/billing', json_decode($body, true), ['X-Signature' => $signature])
        ->assertNotFound();

    Config::set('billing.webhook_secret', '');
    ['body' => $body2, 'signature' => $signature2] = signWebhookPayload([
        'invoice_id' => 1,
        'status' => 'paid',
    ], 'whsec-test');

    $this->postJson('/api/v1/webhooks/billing', json_decode($body2, true), ['X-Signature' => $signature2])
        ->assertStatus(503);
});
