<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;

/**
 * Immutable result of a gateway charge attempt.
 */
final class PaymentResult
{
    public function __construct(
        public readonly InvoiceStatus $status,
        public readonly ?string $reference = null,
    ) {}
}
