<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Flushes the tenant dashboard cache whenever tenant data changes.
 *
 * Observes User, Customer, and Subscription; each carries company_id.
 * Flushing the tenant:{companyId} tag drops that tenant's cached
 * dashboard without touching other tenants.
 */
class FlushTenantDashboardCache
{
    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->flush($model);
    }

    /**
     * Drop the owning tenant's dashboard cache entry.
     */
    private function flush(Model $model): void
    {
        $companyId = $model->getAttribute('company_id');

        if (! is_int($companyId)) {
            return;
        }

        try {
            Cache::tags(["tenant:{$companyId}"])->flush();
        } catch (Throwable) {
            return;
        }
    }
}
