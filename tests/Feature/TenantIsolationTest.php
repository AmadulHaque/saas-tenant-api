<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;

/**
 * Cross-tenant access must resolve to 404 (existence hidden), never leak data.
 */
function tenantFixture(string $emailDomain): array
{
    $company = Company::factory()->withOwner()->create();
    $admin = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'admin',
        'email' => "admin@{$emailDomain}",
    ]);
    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'member',
        'email' => "member@{$emailDomain}",
    ]);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'email' => "customer@{$emailDomain}",
    ]);

    return ['company' => $company, 'admin' => $admin, 'member' => $member, 'customer' => $customer];
}

test('tenant A cannot read tenant B users or customers', function (): void {
    $a = tenantFixture('a.test');
    $b = tenantFixture('b.test');

    $this->actingAs($a['admin'], 'api')
        ->getJson("/api/v1/users/{$b['member']->id}")
        ->assertNotFound();

    $this->actingAs($a['admin'], 'api')
        ->getJson("/api/v1/customers/{$b['customer']->id}")
        ->assertNotFound();
});

test('tenant A cannot modify tenant B users or customers', function (): void {
    $a = tenantFixture('a.test');
    $b = tenantFixture('b.test');

    $this->actingAs($a['admin'], 'api')
        ->patchJson("/api/v1/users/{$b['member']->id}", ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($a['admin'], 'api')
        ->patchJson("/api/v1/customers/{$b['customer']->id}", ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($a['admin'], 'api')
        ->deleteJson("/api/v1/users/{$b['member']->id}")
        ->assertNotFound();

    $this->actingAs($a['admin'], 'api')
        ->deleteJson("/api/v1/customers/{$b['customer']->id}")
        ->assertNotFound();

    expect($b['member']->fresh()->name)->not->toBe('Hacked')
        ->and($b['customer']->fresh()->name)->not->toBe('Hacked')
        ->and($b['member']->fresh()->deleted_at)->toBeNull()
        ->and($b['customer']->fresh()->deleted_at)->toBeNull();
});

test('tenant listings never include other tenants records', function (): void {
    $a = tenantFixture('a.test');
    $b = tenantFixture('b.test');

    $users = $this->actingAs($a['admin'], 'api')->getJson('/api/v1/users')->assertOk();
    $customers = $this->actingAs($a['admin'], 'api')->getJson('/api/v1/customers')->assertOk();

    $userIds = collect($users->json('data'))->pluck('id');
    $customerEmails = collect($customers->json('data'))->pluck('email');

    expect($userIds)->toContain($a['member']->id)
        ->and($userIds)->not->toContain($b['member']->id)
        ->and($customerEmails)->toContain($a['customer']->email)
        ->and($customerEmails)->not->toContain($b['customer']->email);
});

test('company endpoint always returns the actor own company', function (): void {
    $a = tenantFixture('a.test');
    $b = tenantFixture('b.test');

    $this->actingAs($a['admin'], 'api')
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('company.id', $a['company']->id)
        ->assertJsonMissing(['id' => $b['company']->id]);
});

test('invalid identifiers return 404', function (): void {
    $a = tenantFixture('a.test');

    $this->actingAs($a['admin'], 'api')
        ->getJson('/api/v1/users/999999')
        ->assertNotFound();

    $this->actingAs($a['admin'], 'api')
        ->getJson('/api/v1/customers/999999')
        ->assertNotFound();
});

test('non-numeric identifiers are rejected by route constraints', function (): void {
    $a = tenantFixture('a.test');

    $this->actingAs($a['admin'], 'api')
        ->getJson('/api/v1/users/not-a-number')
        ->assertNotFound();
});
