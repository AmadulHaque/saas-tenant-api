<?php

use App\Models\Company;
use App\Models\User;

function companyUserWithRole(Company $company, string $role): User
{
    return User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);
}

test('any company member can view the company', function (string $role): void {
    $company = Company::factory()->withOwner()->create();
    $user = companyUserWithRole($company, $role);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('company.name', $company->name)
        ->assertJsonPath('company.slug', $company->slug)
        ->assertJsonPath('company.owner.email', $company->owner->email);
})->with(['owner', 'admin', 'member']);

test('company view requires authentication', function (): void {
    $this->getJson('/api/v1/company')->assertUnauthorized();
});

test('owner can update the company name', function (): void {
    $company = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->patchJson('/api/v1/company', ['name' => 'Renamed Inc'])
        ->assertOk()
        ->assertJsonPath('company.name', 'Renamed Inc');

    expect($company->fresh()->name)->toBe('Renamed Inc')
        ->and($company->fresh()->slug)->toBe($company->slug);
});

test('non-owners cannot update the company', function (string $role): void {
    $company = Company::factory()->withOwner()->create();
    $user = companyUserWithRole($company, $role);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/company', ['name' => 'Hijack Inc'])
        ->assertForbidden();

    expect($company->fresh()->name)->toBe($company->name);
})->with(['admin', 'member']);

test('company update validates the name field', function (): void {
    $company = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->patchJson('/api/v1/company', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('cross-tenant company access is forbidden', function (): void {
    $company = Company::factory()->withOwner()->create();
    $other = Company::factory()->withOwner()->create();

    $this->actingAs($company->owner, 'api')
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('company.id', $company->id)
        ->assertJsonMissing(['id' => $other->id]);
});

test('authenticated user without company is forbidden', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/company')
        ->assertForbidden();
});
