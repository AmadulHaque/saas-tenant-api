<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Payment gateway contract. Drivers translate a one-off charge into a
 * PaymentResult; asynchronous confirmation flows back through the
 * billing webhook and BillingService::settle().
 */
interface BillingGateway
{
    /**
     * Charge the given amount; never throws for declines — model those as
     * a failed PaymentResult so the invoice trail stays consistent.
     */
    public function charge(int $amountCents, string $currency, string $description): PaymentResult;
}
