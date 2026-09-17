<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    use Concerns\ChecksTenant;

    /**
     * Any member of the company may view it.
     */
    public function view(User $user, Company $company): bool
    {
        return $this->belongsToCompany($user, $company);
    }

    /**
     * Only the owner may update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $this->belongsToCompany($user, $company)
            && $user->role === UserRole::Owner;
    }
}
