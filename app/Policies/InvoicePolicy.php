<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    use Concerns\ChecksTenant;

    /**
     * Only the owner may list the company's invoices (billing scope).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }

    /**
     * Only the owner of the invoiced company may view an invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCompany($user, $invoice)
            && $user->role === UserRole::Owner;
    }
}
