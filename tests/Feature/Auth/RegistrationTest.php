<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseHas;

test('registration creates tenant, owner, and usable token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.test',
        'password' => 'super-secret-123',
        'company_name' => 'Jane Co',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'role', 'company'],
            'company' => ['id', 'name', 'slug'],
            'token',
        ])
        ->assertJsonPath('user.email', 'jane@example.test')
        ->assertJsonPath('user.role', 'owner')
        ->assertJsonPath('company.name', 'Jane Co');

    assertDatabaseHas('users', [
        'email' => 'jane@example.test',
        'role' => 'owner',
    ]);

    $company = Company::query()->where('slug', $response->json('company.slug'))->first();
    $user = User::query()->where('email', 'jane@example.test')->first();

    expect($company)->not->toBeNull()
        ->and($company->owner_id)->toBe($user->id)
        ->and($user->company_id)->toBe($company->id)
        ->and(Hash::check('super-secret-123', $user->password))->toBeTrue();

    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertOk()
        ->assertJsonPath('user.email', 'jane@example.test');
});

test('registration validates required fields and uniqueness', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'taken@example.test',
        'password' => 'short',
        'company_name' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'company_name']);
});

test('identical company names receive distinct slugs', function () {
    $payload = fn (string $email): array => [
        'name' => 'Owner',
        'email' => $email,
        'password' => 'super-secret-123',
        'company_name' => 'Duplicate Name Inc',
    ];

    $first = $this->postJson('/api/v1/auth/register', $payload('first@example.test'))->assertCreated();
    $second = $this->postJson('/api/v1/auth/register', $payload('second@example.test'))->assertCreated();

    expect($first->json('company.slug'))->not->toBe($second->json('company.slug'));
});

test('registration response never exposes the password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'safe@example.test',
        'password' => 'super-secret-123',
        'company_name' => 'Safe Co',
    ]);

    $response->assertCreated()
        ->assertJsonMissing(['password' => 'super-secret-123'])
        ->assertDontSee('super-secret-123');
});
