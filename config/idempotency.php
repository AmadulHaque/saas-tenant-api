<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | How long replayable request/response pairs are kept, keyed by the
    | client's Idempotency-Key header.
    |
    */

    'ttl_minutes' => env('IDEMPOTENCY_TTL_MINUTES', 10),
];
