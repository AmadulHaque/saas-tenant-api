<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Passport\Passport;

test('me returns the authenticated user with company context', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

    Passport::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'member')
        ->assertJsonPath('user.company.name', $company->name)
        ->assertJsonPath('user.company.slug', $company->slug);
});

test('me requires authentication', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
