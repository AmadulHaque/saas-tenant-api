<?php

use App\Models\Company;
use App\Models\User;

function loginUserViaApi(array $attributes = []): User
{
    $company = Company::factory()->create();
    $attributes = [
        'password' => $attributes['password'] ?? 'password',
        ...$attributes,
    ];

    return User::factory()->create([...$attributes, 'company_id' => $company->id, 'role' => 'owner']);
}

test('login issues a usable token for valid credentials', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['email' => 'login@example.test', 'company_id' => $company->id, 'role' => 'owner']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token'])
        ->assertJsonPath('user.email', 'login@example.test');

    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertOk();
});

test('login rejects a wrong password', function (): void {
    loginUserViaApi(['email' => 'wrongpw@example.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'wrongpw@example.test',
        'password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login rejects an unknown email', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@example.test',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('soft-deleted users cannot log in', function (): void {
    $user = loginUserViaApi(['email' => 'deleted@example.test']);
    $user->delete();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'deleted@example.test',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('login is rate limited to five attempts per minute', function (): void {
    loginUserViaApi(['email' => 'throttled@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'throttled@example.test',
            'password' => 'not-the-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'throttled@example.test',
        'password' => 'not-the-password',
    ])->assertTooManyRequests();
});

test('login validates payload shape', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
