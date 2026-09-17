<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Passport\Passport;

test('logout revokes the current token', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();
    $token = $login->json('token');

    $headers = ['Authorization' => 'Bearer '.$token];

    $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();

    expect($user->tokens()->where('revoked', false)->count())->toBe(0);

    // Reset resolved guards so the revoked token is re-validated on a fresh request.
    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
});

test('logout requires authentication', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

test('authenticated users reach protected routes via actingAs', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

    Passport::actingAs($user);

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('user.role', 'admin');
});
