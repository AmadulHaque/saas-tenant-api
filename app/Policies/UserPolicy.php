<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    use Concerns\ChecksTenant;

    /**
     * Owners and admins may list the company's users.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Owners and admins may view users of their own company.
     */
    public function view(User $user, User $target): bool
    {
        return $this->canManage($user)
            && $this->belongsToCompany($user, $target);
    }

    /**
     * Owners and admins may invite new users to their company.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Owners and admins may update users of their own company,
     * except the owner account itself.
     */
    public function update(User $user, User $target): bool
    {
        return $this->canManage($user)
            && $this->belongsToCompany($user, $target)
            && $target->role !== UserRole::Owner;
    }

    /**
     * Owners and admins may remove users of their own company,
     * except the owner account and themselves.
     */
    public function delete(User $user, User $target): bool
    {
        return $this->canManage($user)
            && $this->belongsToCompany($user, $target)
            && $target->role !== UserRole::Owner
            && ! $user->is($target);
    }
}
