<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $price_cents
 * @property BillingInterval $billing_interval
 * @property array<string, int|null>|null $limits
 * @property bool $is_active
 */
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'price_cents',
        'billing_interval',
        'limits',
        'is_active',
    ];

    protected const FEATURE_MAX_USERS = 'max_users';

    protected const FEATURE_MAX_CUSTOMERS = 'max_customers';

    /**
     * Active subscriptions sold on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Maximum allowed users; null means unlimited.
     */
    public function maxUsers(): ?int
    {
        return $this->limitFor(self::FEATURE_MAX_USERS);
    }

    /**
     * Maximum allowed customers; null means unlimited.
     */
    public function maxCustomers(): ?int
    {
        return $this->limitFor(self::FEATURE_MAX_CUSTOMERS);
    }

    /**
     * Read a configured feature limit from the limits payload.
     */
    private function limitFor(string $feature): ?int
    {
        $limit = $this->limits[$feature] ?? null;

        return is_int($limit) ? $limit : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
