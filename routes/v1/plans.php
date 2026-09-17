<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Plan\IndexController;
use App\Http\Controllers\Api\V1\Plan\ShowController;
use Illuminate\Support\Facades\Route;

Route::get('/plans', IndexController::class)->name('plans.index');
Route::get('/plans/{id}', ShowController::class)->whereNumber('id')->name('plans.show');
