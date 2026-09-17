<?php

use App\Models\Company;
use App\Models\User;

function actingCompanyUser(Company $company, string $role): User
{
    return User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);
}

test('owners and admins can list company users', function (string $role): void {
    $company = Company::factory()->withOwner()->create();
    $actor = actingCompanyUser($company, $role);

    $this->actingAs($actor, 'api')
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']], 'links', 'meta'])
        ->assertJsonCount(2, 'data');
})->with(['owner', 'admin']);

test('members cannot list company users', function (): void {
    $company = Company::factory()->withOwner()->create();
    $member = actingCompanyUser($company, 'member');

    $this->actingAs($member, 'api')
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

test('user list is paginated with a per_page cap', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');
    User::factory()->count(12)->create(['company_id' => $company->id, 'role' => 'member']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?per_page=5&page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 14);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?per_page=100')
        ->assertOk()
        ->assertJsonCount(14, 'data');
});

test('user list supports search and role filters', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');
    User::factory()->create(['name' => 'Zara Unique', 'company_id' => $company->id, 'role' => 'member']);
    User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?search=Zara')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zara Unique');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?search=zara')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zara Unique');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?role=member')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?role=owner')
        ->assertUnprocessable();
});

test('admins can create users with admin or member roles', function (string $role): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');

    $response = $this->actingAs($admin, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'New Person',
            'email' => 'new-person@example.test',
            'password' => 'a-secure-password',
            'role' => $role,
        ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'new-person@example.test')
        ->assertJsonPath('user.role', $role);

    expect(User::query()->where('email', 'new-person@example.test')->first()->company_id)
        ->toBe($company->id);
})->with(['admin', 'member']);

test('users cannot be created with the owner role', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Sneaky Owner',
            'email' => 'sneaky@example.test',
            'password' => 'a-secure-password',
            'role' => 'owner',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

test('user creation validates duplicates and password strength', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => $company->owner->email,
            'password' => 'short',
            'role' => 'member',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('admins can view and update non-owner users', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');
    $member = actingCompanyUser($company, 'member');

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/users/{$member->id}")
        ->assertOk()
        ->assertJsonPath('user.id', $member->id);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/users/{$member->id}", ['name' => 'Renamed Member', 'role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('user.name', 'Renamed Member')
        ->assertJsonPath('user.role', 'admin');
});

test('owner accounts cannot be modified or deleted via the API', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');
    $owner = $company->owner;

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/users/{$owner->id}", ['role' => 'member'])
        ->assertForbidden();

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/v1/users/{$owner->id}")
        ->assertForbidden();

    expect($owner->fresh()->deleted_at)->toBeNull();
});

test('admins cannot delete themselves', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/v1/users/{$admin->id}")
        ->assertForbidden();

    expect($admin->fresh()->deleted_at)->toBeNull();
});

test('deleting a user soft-deletes and revokes their tokens', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = actingCompanyUser($company, 'admin');
    $member = actingCompanyUser($company, 'member');

    $member->createToken('api');

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/v1/users/{$member->id}")
        ->assertNoContent();

    expect($member->fresh()->deleted_at)->not->toBeNull()
        ->and($member->tokens()->where('revoked', false)->count())->toBe(0);
});
