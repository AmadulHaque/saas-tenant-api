<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\User\DestroyController;
use App\Http\Controllers\Api\V1\User\IndexController;
use App\Http\Controllers\Api\V1\User\ShowController;
use App\Http\Controllers\Api\V1\User\StoreController;
use App\Http\Controllers\Api\V1\User\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/users', IndexController::class)->name('users.index');
Route::post('/users', StoreController::class)->name('users.store');
Route::get('/users/{id}', ShowController::class)->whereNumber('id')->name('users.show');
Route::patch('/users/{id}', UpdateController::class)->whereNumber('id')->name('users.update');
Route::delete('/users/{id}', DestroyController::class)->whereNumber('id')->name('users.destroy');
