<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Usage\IndexController;
use App\Http\Controllers\Api\V1\Usage\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/usage', IndexController::class)->name('usage.index');
Route::post('/usage', StoreController::class)->name('usage.store');
