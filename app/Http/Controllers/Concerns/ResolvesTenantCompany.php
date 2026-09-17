<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * Resolves the authenticated principal's company or aborts with 403.
 */
trait ResolvesTenantCompany
{
    /**
     * The authenticated user's company, guaranteed to exist.
     */
    protected function resolveCompany(Request $request): Company
    {
        $company = $request->user('api')->company;

        abort_if($company === null, 403, 'No company context.');

        return $company;
    }
}
