<?php

declare(strict_types=1);

return [
    /*
    * Default payment gateway driver (BillingGateway implementation bound
    * in AppServiceProvider).
    */
    'gateway' => env('BILLING_GATEWAY', 'fake'),

    /*
    * Three-letter ISO currency code for charges.
    */
    'currency' => env('BILLING_CURRENCY', 'usd'),

    /*
    * HMAC-SHA256 secret for the billing webhook signature
    * (X-Signature over the raw request body).
    */
    'webhook_secret' => env('BILLING_WEBHOOK_SECRET', ''),

    'fake' => [
        /*
        * paid|pending|failed — outcome the fake driver returns for charges.
        */
        'behavior' => env('BILLING_FAKE_BEHAVIOR', 'paid'),
    ],
];
