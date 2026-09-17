<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;

function staffUser(Company $company, string $role): User
{
    return User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);
}

test('all members can list customers', function (string $role): void {
    $company = Company::factory()->withOwner()->create();
    $user = staffUser($company, $role);
    Customer::factory()->count(3)->create(['company_id' => $company->id]);
    Customer::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/customers')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'status']], 'links', 'meta'])
        ->assertJsonCount(3, 'data');
})->with(['owner', 'admin', 'member']);

test('customers support search, status filter, and pagination', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = staffUser($company, 'admin');

    Customer::factory()->create(['company_id' => $company->id, 'name' => 'Ada Lovelace', 'email' => 'ada@calc.test']);
    Customer::factory()->inactive()->create(['company_id' => $company->id, 'name' => 'Grace Hopper']);
    Customer::factory()->count(20)->create(['company_id' => $company->id]);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/customers?search=lovelace')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ada Lovelace');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/customers?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Grace Hopper');

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/customers?per_page=10&page=1')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 22);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/customers?per_page=1000')
        ->assertUnprocessable();
});

test('admins can create customers', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = staffUser($company, 'admin');

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/customers', [
            'name' => 'New Customer',
            'email' => 'new@customer.test',
            'phone' => '+8801700000000',
        ])
        ->assertCreated()
        ->assertJsonPath('customer.email', 'new@customer.test')
        ->assertJsonPath('customer.status', 'active')
        ->assertJsonPath('customer.company_id', null);

    expect(Customer::query()->where('email', 'new@customer.test')->first()->company_id)
        ->toBe($company->id);
});

test('members cannot create, update, or delete customers', function (): void {
    $company = Company::factory()->withOwner()->create();
    $member = staffUser($company, 'member');
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->actingAs($member, 'api')
        ->postJson('/api/v1/customers', ['name' => 'X', 'email' => 'x@x.test'])
        ->assertForbidden();

    $this->actingAs($member, 'api')
        ->patchJson("/api/v1/customers/{$customer->id}", ['name' => 'Y'])
        ->assertForbidden();

    $this->actingAs($member, 'api')
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertForbidden();
});

test('customer emails are unique per company only', function (): void {
    $companyA = Company::factory()->withOwner()->create();
    $companyB = Company::factory()->withOwner()->create();

    Customer::factory()->create(['company_id' => $companyA->id, 'email' => 'shared@shop.test']);

    $this->actingAs(staffUser($companyB, 'admin'), 'api')
        ->postJson('/api/v1/customers', ['name' => 'B Copy', 'email' => 'shared@shop.test'])
        ->assertCreated();

    $this->actingAs(staffUser($companyA, 'admin'), 'api')
        ->postJson('/api/v1/customers', ['name' => 'A Dup', 'email' => 'shared@shop.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('deleted customers free their email for reuse', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = staffUser($company, 'admin');
    $customer = Customer::factory()->create(['company_id' => $company->id, 'email' => 'gone@shop.test']);

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertNoContent();

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/customers', ['name' => 'Reuse', 'email' => 'gone@shop.test'])
        ->assertCreated();
});

test('customers can be shown and updated', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = staffUser($company, 'admin');
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('customer.id', $customer->id);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Updated Name',
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('customer.name', 'Updated Name')
        ->assertJsonPath('customer.status', 'inactive');
});

test('updating a customer to a duplicate email fails', function (): void {
    $company = Company::factory()->withOwner()->create();
    $admin = staffUser($company, 'admin');
    $first = Customer::factory()->create(['company_id' => $company->id, 'email' => 'first@shop.test']);
    $second = Customer::factory()->create(['company_id' => $company->id, 'email' => 'second@shop.test']);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/customers/{$second->id}", ['email' => $first->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/customers/{$second->id}", ['email' => $second->email])
        ->assertOk();
});
