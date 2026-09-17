<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    use Concerns\ChecksTenant;

    /**
     * Only the owner may view the company's subscription (billing scope).
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $this->belongsToCompany($user, $subscription)
            && $user->role === UserRole::Owner;
    }

    /**
     * Only the owner may manage the company's subscription.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }

    /**
     * Only the owner may update the company's subscription.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return $this->belongsToCompany($user, $subscription)
            && $user->role === UserRole::Owner;
    }
}
