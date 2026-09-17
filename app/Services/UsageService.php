<?php

namespace App\Services;

use App\Models\Company;
use App\Models\UsageRecord;

/**
 * Records subscription feature usage for a tenant.
 *
 * Usage rows are append-only ledger entries; corrections are recorded
 * as negative deltas rather than updates.
 */
class UsageService
{
    /**
     * Record a signed usage delta for the company.
     */
    public function record(
        Company $company,
        string $feature,
        int $delta,
        ?array $metadata = null,
    ): UsageRecord {
        return UsageRecord::query()->create([
            'company_id' => $company->id,
            'feature' => $feature,
            'delta' => $delta,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Total recorded usage for a feature (0 when the tenant has none).
     */
    public function total(Company $company, string $feature): int
    {
        return (int) UsageRecord::query()
            ->where('company_id', $company->id)
            ->where('feature', $feature)
            ->sum('delta');
    }
}
