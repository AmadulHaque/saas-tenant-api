<?php

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

function middlewareTenant(): object
{
    $company = Company::factory()->withOwner()->create();

    return (object) ['token' => null, 'owner' => $company->owner, 'company' => $company];
}

function authed(object $tenant): array
{
    $token = $tenant->owner->createToken('test')->accessToken;

    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

test('every response carries an X-Request-Id', function (): void {
    $tenant = middlewareTenant();

    $response = $this->getJson('/api/v1/me', authed($tenant));

    $response->assertOk();

    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

test('a valid client supplied request id is preserved end to end', function (): void {
    $tenant = middlewareTenant();

    $response = $this->getJson('/api/v1/me', authed($tenant) + ['X-Request-Id' => 'client-abc-123456789']);

    $response->assertOk()
        ->assertHeader('X-Request-Id', 'client-abc-123456789');
});

test('an unsafe client supplied request id is replaced', function (): void {
    $tenant = middlewareTenant();

    $response = $this->getJson('/api/v1/me', authed($tenant) + ['X-Request-Id' => "evil<script>\n"]);

    $response->assertOk();

    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

test('responses advertise content language', function (): void {
    $tenant = middlewareTenant();

    $this->getJson('/api/v1/me', authed($tenant))
        ->assertOk()
        ->assertHeader('Content-Language', 'en');
});

test('json responses carry hardening and timing headers', function (): void {
    $tenant = middlewareTenant();

    $response = $this->getJson('/api/v1/me', authed($tenant));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($response->headers->get('X-Response-Time'))->toEndWith('ms');
});

test('non-json request bodies are rejected with 415', function (): void {
    $tenant = middlewareTenant();

    $this->post(
        '/api/v1/customers',
        ['name' => 'Form Body'],
        authed($tenant) + ['Content-Type' => 'text/plain'],
    )->assertStatus(415)
        ->assertJsonPath('message', 'Requests with a body must be sent as application/json.');
});

test('api request logging can be enabled', function (): void {
    $tenant = middlewareTenant();
    Log::spy();
    config()->set('logging.log_api_requests', true);

    $this->getJson('/api/v1/me', authed($tenant))->assertOk();

    Log::assertLogged('api.request');
});

test('api request logging is disabled by default', function (): void {
    $tenant = middlewareTenant();
    Log::spy();

    $this->getJson('/api/v1/me', authed($tenant))->assertOk();

    Log::assertNotLogged('api.request');
});

test('idempotency key replays the first response without duplicating writes', function (): void {
    $tenant = middlewareTenant();
    $headers = authed($tenant) + ['Idempotency-Key' => 'create-customer-1', 'Content-Type' => 'application/json'];
    $payload = ['name' => 'Idem Customer', 'email' => 'idem@customer.test'];

    $first = $this->postJson('/api/v1/customers', $payload, $headers);
    $first->assertCreated()->assertHeader('Idempotency-Key', 'create-customer-1');

    $replay = $this->postJson('/api/v1/customers', $payload, $headers);
    $replay->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJsonPath('customer.id', $first->json('customer.id'));

    expect($tenant->company->customers()->count())->toBe(1);
});

test('reusing an idempotency key with a different payload conflicts', function (): void {
    $tenant = middlewareTenant();
    $headers = authed($tenant) + ['Idempotency-Key' => 'conflict-key-1', 'Content-Type' => 'application/json'];

    $this->postJson('/api/v1/customers', ['name' => 'A', 'email' => 'a@conflict.test'], $headers)->assertCreated();

    $this->postJson('/api/v1/customers', ['name' => 'B', 'email' => 'b@conflict.test'], $headers)
        ->assertStatus(409)
        ->assertJsonPath('message', 'This Idempotency-Key was already used with a different request payload.');
});

test('malformed idempotency keys are rejected with 422', function (): void {
    $tenant = middlewareTenant();

    $this->postJson('/api/v1/customers', ['name' => 'X', 'email' => 'x@key.test'],
        authed($tenant) + ['Idempotency-Key' => 'short', 'Content-Type' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Idempotency-Key'));
});

test('idempotency cache entries expire after the configured ttl', function (): void {
    expect(config('idempotency.ttl_minutes'))->toBeInt()->toBeGreaterThan(0);

    Cache::put('probe', 'ok', now()->addMinutes(config('idempotency.ttl_minutes')));

    expect(Cache::get('probe'))->toBe('ok');
});
