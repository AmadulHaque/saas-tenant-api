<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Number of months in one billing period.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
