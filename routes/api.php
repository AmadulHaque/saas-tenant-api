<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

require __DIR__ . '/v1/auth.php';

Route::middleware(['auth:api', 'throttle:authenticated'])->group(function (): void {
    require __DIR__ . '/v1/me.php';
});
