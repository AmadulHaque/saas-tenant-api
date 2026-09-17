<?php

use App\Models\Company;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\UsageService;

function usageTenant(): array
{
    $company = Company::factory()->withOwner()->create();
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $member = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

    return ['company' => $company, 'owner' => $company->owner, 'admin' => $admin, 'member' => $member];
}

test('owner and admin can record usage with metadata', function () {
    ['company' => $company, 'owner' => $owner, 'admin' => $admin] = usageTenant();

    foreach ([$owner, $admin] as $user) {
        $this->actingAs($user, 'api')
            ->postJson('/api/v1/usage', [
                'feature' => 'Api_Calls',
                'delta' => 25,
                'metadata' => ['source' => 'webhook'],
            ])
            ->assertCreated()
            ->assertJsonPath('usage.feature', 'api_calls')
            ->assertJsonPath('usage.delta', 25);
    }

    expect($company->usageRecords()->count())->toBe(2)
        ->and(app(UsageService::class)->total($company, 'api_calls'))->toBe(50);
});

test('members cannot read or record usage', function () {
    ['company' => $company, 'member' => $member] = usageTenant();
    UsageRecord::factory()->create(['company_id' => $company->id]);

    $this->actingAs($member, 'api')
        ->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => 1])
        ->assertForbidden();

    $this->actingAs($member, 'api')
        ->getJson('/api/v1/usage')
        ->assertForbidden();

    expect($company->usageRecords()->count())->toBe(1);
});

test('usage listing is paginated and feature filtered', function () {
    ['company' => $company, 'owner' => $owner] = usageTenant();
    UsageRecord::factory()->count(3)->create(['company_id' => $company->id, 'feature' => 'api_calls']);
    UsageRecord::factory()->create(['company_id' => $company->id, 'feature' => 'exports']);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/usage?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 4);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/usage?feature=exports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);
});

test('usage data never leaks across tenants', function () {
    ['company' => $companyA, 'owner' => $ownerA] = usageTenant();
    $companyB = Company::factory()->withOwner()->create();
    UsageRecord::factory()->count(2)->create(['company_id' => $companyB->id]);

    $this->actingAs($ownerA, 'api')
        ->getJson('/api/v1/usage')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(app(UsageService::class)->total($companyA, 'api_calls'))->toBe(0);
});

test('usage validation rejects bad payloads', function () {
    ['owner' => $owner] = usageTenant();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['delta' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['feature']);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delta']);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['feature' => str_repeat('x', 51), 'delta' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['feature']);

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => 5, 'metadata' => ['k' => ['nested']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metadata.k']);
});

test('negative deltas record corrections', function () {
    ['company' => $company, 'owner' => $owner] = usageTenant();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => 100])
        ->assertCreated();

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/usage', ['feature' => 'api_calls', 'delta' => -30])
        ->assertCreated();

    expect(app(UsageService::class)->total($company, 'api_calls'))->toBe(70);
});
