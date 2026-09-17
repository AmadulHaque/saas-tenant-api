<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
});

Route::middleware(['auth:api', 'throttle:authenticated'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
