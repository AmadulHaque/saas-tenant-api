<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
Route::get('/customers/{id}', [CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
Route::patch('/customers/{id}', [CustomerController::class, 'update'])->whereNumber('id')->name('customers.update');
Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->whereNumber('id')->name('customers.destroy');
