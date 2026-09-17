<?php

namespace App\Policies\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared tenant-ownership checks for policies.
 */
trait ChecksTenant
{
    /**
     * Whether the user belongs to the same company as the tenant-owned record.
     */
    protected function belongsToCompany(User $user, Model $model): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        $modelCompanyId = $model instanceof Company
            ? $model->id
            : $model->getAttribute('company_id');

        return (int) $modelCompanyId === (int) $user->company_id;
    }

    /**
     * Whether the user holds a role allowed to manage tenant resources.
     */
    protected function canManage(User $user): bool
    {
        return $user->role !== null && $user->role->canManage();
    }
}
