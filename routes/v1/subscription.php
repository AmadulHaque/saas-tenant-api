<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/plans/{id}', [PlanController::class, 'show'])->whereNumber('id')->name('plans.show');

Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
Route::post('/subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
Route::patch('/subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
