<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Subscription;

/**
 * Authorization helpers for subscription management, which is scoped
 * to the actor's company rather than an existing subscription row.
 */
trait AuthorizesSubscription
{
    /**
     * Authorize a subscription ability against the actor's company.
     */
    protected function authorizeSubscription(string $ability, Company $company): void
    {
        $this->authorize($ability, (new Subscription)->forceFill(['company_id' => $company->id]));
    }
}
