<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces plan feature limits for tenant resource creation.
 *
 * Counting and creation happen inside a transaction that holds a row
 * lock on the company, so concurrent creations cannot exceed a limit.
 */
class SubscriptionLimitService
{
    /**
     * Feature key to the model counted against the limit.
     *
     * @var array<string, class-string>
     */
    private const COUNTED_MODELS = [
        'max_users' => User::class,
        'max_customers' => Customer::class,
    ];

    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * Run the given creation callback if the plan limit allows it.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $create
     * @return TReturn
     */
    public function enforce(Company $company, string $feature, Closure $create): mixed
    {
        $limit = $this->subscriptions->featureLimit($company, $feature);

        if ($limit === null) {
            return $create();
        }

        return DB::transaction(function () use ($company, $feature, $limit, $create): mixed {
            Company::query()->whereKey($company->id)->lockForUpdate()->get();

            $counted = self::COUNTED_MODELS[$feature];

            $currentCount = $counted::query()
                ->where('company_id', $company->id)
                ->count();

            if ($currentCount >= $limit) {
                throw ValidationException::withMessages([
                    'limit' => sprintf(
                        'The plan limit of %d has been reached (%s).',
                        $limit,
                        str_replace('_', ' ', $feature)
                    ),
                ]);
            }

            return $create();
        });
    }
}
