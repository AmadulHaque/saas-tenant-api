<?php

declare(strict_types=1);

/**
 * Blank env entries ("KEY=") resolve to empty strings, not null, so plain
 * env() defaults never apply. These helpers fall back to the default for
 * both null and blank values.
 */
$envBool = fn (string $key, bool $default): bool => (($value = env($key)) === null || $value === '')
    ? $default
    : (bool) $value;

$envInt = fn (string $key, int $default): int => (($value = env($key)) === null || $value === '')
    ? $default
    : (int) $value;

return [
    'force_https' => $envBool('SECURITY_FORCE_HTTPS', false),

    'hsts' => [
        'enabled' => $envBool('SECURITY_HSTS_ENABLED', true),
        'max_age' => $envInt('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => $envBool('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => $envBool('SECURITY_HSTS_PRELOAD', false),
    ],

    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    'trusted_hosts' => array_values(array_filter(array_map(
        mb_trim(...),
        explode(',', (string) env('TRUSTED_HOSTS', '')),
    ))),
];
