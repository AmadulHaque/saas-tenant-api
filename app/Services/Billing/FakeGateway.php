<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Deterministic local driver: behaviour is config-driven
 * (billing.fake.behavior = paid|pending|failed) so tests and demos can
 * exercise every path without external services.
 */
final class FakeGateway implements BillingGateway
{
    public function charge(int $amountCents, string $currency, string $description): PaymentResult
    {
        $behavior = Config::string('billing.fake.behavior', 'paid');

        $status = InvoiceStatus::tryFrom($behavior) ?? InvoiceStatus::Paid;

        if ($status === InvoiceStatus::Pending) {
            return new PaymentResult($status, 'fake_pending_'.Str::ulid()->toBase32());
        }

        return new PaymentResult($status, 'fake_'.Str::ulid()->toBase32());
    }
}
