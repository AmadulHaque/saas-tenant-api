<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    use Concerns\ChecksTenant;

    /**
     * Any member of the company may list its customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Any member of the company may view its customers.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $this->belongsToCompany($user, $customer);
    }

    /**
     * Owners and admins may create customers.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Owners and admins may update their company's customers.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $this->canManage($user)
            && $this->belongsToCompany($user, $customer);
    }

    /**
     * Owners and admins may delete their company's customers.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $this->canManage($user)
            && $this->belongsToCompany($user, $customer);
    }
}
