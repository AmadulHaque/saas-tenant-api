<?php

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;

function policyUser(int $id, ?int $companyId, UserRole $role): User
{
    return (new User)->forceFill([
        'id' => $id,
        'company_id' => $companyId,
        'role' => $role,
    ]);
}

test('company policy grants view to members and update to owner only', function () {
    $company = (new Company)->forceFill(['id' => 1, 'company_id' => 1]);
    $other = (new Company)->forceFill(['id' => 2, 'company_id' => 2]);

    $owner = policyUser(1, 1, UserRole::Owner);
    $admin = policyUser(2, 1, UserRole::Admin);
    $member = policyUser(3, 1, UserRole::Member);
    $outsider = policyUser(4, 2, UserRole::Admin);
    $orphan = policyUser(5, null, UserRole::Admin);

    $policy = new CompanyPolicy;

    expect($policy->view($owner, $company))->toBeTrue()
        ->and($policy->view($member, $company))->toBeTrue()
        ->and($policy->view($outsider, $company))->toBeFalse()
        ->and($policy->view($orphan, $company))->toBeFalse()
        ->and($policy->update($owner, $company))->toBeTrue()
        ->and($policy->update($admin, $company))->toBeFalse()
        ->and($policy->update($member, $company))->toBeFalse()
        ->and($policy->update($outsider, $company))->toBeFalse();
});

test('user policy allows admins to manage non-owner users only', function () {
    $policy = new UserPolicy;

    $owner = policyUser(1, 1, UserRole::Owner);
    $admin = policyUser(2, 1, UserRole::Admin);
    $member = policyUser(3, 1, UserRole::Member);
    $otherAdmin = policyUser(4, 1, UserRole::Admin);
    $outsider = policyUser(5, 2, UserRole::Member);

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->viewAny($member))->toBeFalse()
        ->and($policy->view($admin, $member))->toBeTrue()
        ->and($policy->view($admin, $outsider))->toBeFalse()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->create($member))->toBeFalse()
        ->and($policy->update($admin, $member))->toBeTrue()
        ->and($policy->update($admin, $owner))->toBeFalse()
        ->and($policy->update($admin, $outsider))->toBeFalse()
        ->and($policy->delete($admin, $otherAdmin))->toBeTrue()
        ->and($policy->delete($admin, $admin))->toBeFalse()
        ->and($policy->delete($admin, $owner))->toBeFalse()
        ->and($policy->delete($member, $member))->toBeFalse();
});

test('customer policy grants read to all members and writes to managers', function () {
    $policy = new CustomerPolicy;

    $customer = (new Customer)->forceFill([
        'id' => 1,
        'company_id' => 1,
        'status' => CustomerStatus::Active,
    ]);
    $foreignCustomer = (new Customer)->forceFill([
        'id' => 2,
        'company_id' => 2,
        'status' => CustomerStatus::Active,
    ]);

    $owner = policyUser(1, 1, UserRole::Owner);
    $admin = policyUser(2, 1, UserRole::Admin);
    $member = policyUser(3, 1, UserRole::Member);

    expect($policy->viewAny($member))->toBeTrue()
        ->and($policy->view($member, $customer))->toBeTrue()
        ->and($policy->view($member, $foreignCustomer))->toBeFalse()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->create($member))->toBeFalse()
        ->and($policy->update($admin, $customer))->toBeTrue()
        ->and($policy->update($member, $customer))->toBeFalse()
        ->and($policy->update($admin, $foreignCustomer))->toBeFalse()
        ->and($policy->delete($owner, $customer))->toBeTrue()
        ->and($policy->delete($member, $customer))->toBeFalse();
});

test('subscription policy is restricted to the owner', function () {
    $policy = new SubscriptionPolicy;

    $subscription = (new Subscription)->forceFill([
        'id' => 1,
        'company_id' => 1,
    ]);

    $owner = policyUser(1, 1, UserRole::Owner);
    $admin = policyUser(2, 1, UserRole::Admin);
    $outsiderOwner = policyUser(3, 2, UserRole::Owner);

    expect($policy->view($owner, $subscription))->toBeTrue()
        ->and($policy->view($admin, $subscription))->toBeFalse()
        ->and($policy->view($outsiderOwner, $subscription))->toBeFalse()
        ->and($policy->create($owner))->toBeTrue()
        ->and($policy->create($admin))->toBeFalse()
        ->and($policy->update($owner, $subscription))->toBeTrue()
        ->and($policy->update($admin, $subscription))->toBeFalse();
});
