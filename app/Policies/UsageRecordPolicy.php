<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UsageRecordPolicy
{
    /**
     * Determine who can view company usage records.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminArea($user);
    }

    /**
     * Determine who can record company usage.
     */
    public function create(User $user): bool
    {
        return $this->isAdminArea($user);
    }

    /**
     * Usage management is owner/admin territory; members are read-free.
     */
    private function isAdminArea(User $user): bool
    {
        return $user->company_id !== null
            && in_array($user->role, [UserRole::Owner, UserRole::Admin], true);
    }
}
