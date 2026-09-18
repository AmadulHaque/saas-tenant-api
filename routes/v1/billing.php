<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Billing\CheckoutController;
use App\Http\Controllers\Api\V1\Billing\InvoiceIndexController;
use App\Http\Controllers\Api\V1\Billing\InvoiceShowController;
use Illuminate\Support\Facades\Route;

Route::post('/billing/checkout', CheckoutController::class)->name('billing.checkout');
Route::get('/billing/invoices', InvoiceIndexController::class)->name('billing.invoices.index');
Route::get('/billing/invoices/{invoice}', InvoiceShowController::class)->name('billing.invoices.show');
