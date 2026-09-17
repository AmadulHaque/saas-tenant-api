<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Subscription\ShowController;
use App\Http\Controllers\Api\V1\Subscription\StoreController;
use App\Http\Controllers\Api\V1\Subscription\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/subscription', ShowController::class)->name('subscription.show');
Route::post('/subscription', StoreController::class)->name('subscription.store');
Route::patch('/subscription', UpdateController::class)->name('subscription.update');
