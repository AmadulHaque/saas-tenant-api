<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Customer\DestroyController;
use App\Http\Controllers\Api\V1\Customer\IndexController;
use App\Http\Controllers\Api\V1\Customer\ShowController;
use App\Http\Controllers\Api\V1\Customer\StoreController;
use App\Http\Controllers\Api\V1\Customer\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', IndexController::class)->name('customers.index');
Route::post('/customers', StoreController::class)->name('customers.store');
Route::get('/customers/{id}', ShowController::class)->whereNumber('id')->name('customers.show');
Route::patch('/customers/{id}', UpdateController::class)->whereNumber('id')->name('customers.update');
Route::delete('/customers/{id}', DestroyController::class)->whereNumber('id')->name('customers.destroy');
