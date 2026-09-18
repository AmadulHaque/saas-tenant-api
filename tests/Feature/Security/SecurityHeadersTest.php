<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

test('api responses carry defense-in-depth headers', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable();
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Content-Security-Policy'))
        ->toBe("default-src 'none'; frame-ancestors 'none'");
});

test('hsts is absent over plain http when https is not forced', function (): void {
    Config::set('security.force_https', false);

    $response = $this->get('/up');

    $response->assertOk();
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy'))->toBeNull();
});

test('hsts is emitted for secure requests with configured policy', function (): void {
    Config::set('security.hsts.preload', true);

    $response = $this->get('https://localhost/up');

    $response->assertOk();
    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains; preload');
});

test('hsts honours the enabled flag', function (): void {
    Config::set('security.hsts.enabled', false);

    $response = $this->get('https://localhost/up');

    $response->assertOk();
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

test('insecure requests are redirected when https is forced', function (): void {
    Config::set('security.force_https', true);

    $response = $this->get('http://localhost/up');

    $response->assertRedirect('https://localhost/up');
    expect($response->status())->toBe(301);
});

test('a host outside the allow-list is rejected', function (): void {
    Config::set('security.trusted_hosts', ['api.acme.test']);

    $response = $this->get('http://evil.test/up');

    $response->assertBadRequest();
});

test('an empty allow-list accepts any host', function (): void {
    Config::set('security.trusted_hosts', []);

    $this->get('http://anything.test/up')->assertOk();
});
