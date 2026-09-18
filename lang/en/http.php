<?php

declare(strict_types=1);

return [
    'unsupported_media_type' => 'Requests with a body must be sent as application/json.',
    'retry_soon' => 'The request is still being processed. Please retry shortly.',
    'payment_failed' => 'The payment was declined. No subscription was activated; the invoice records the failure.',
    'idempotency' => [
        'invalid' => 'The Idempotency-Key header must be 8-128 characters of letters, digits, dots, colons, underscores, or hyphens.',
        'conflict' => 'This Idempotency-Key was already used with a different request payload.',
    ],
];
