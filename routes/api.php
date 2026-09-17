<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/v1/auth.php';

    Route::middleware(['auth:api', 'throttle:authenticated'])->group(function (): void {
        require __DIR__.'/v1/me.php';
        require __DIR__.'/v1/company.php';
        require __DIR__.'/v1/users.php';
        require __DIR__.'/v1/customers.php';
    });
});
