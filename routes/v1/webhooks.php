<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Webhooks\BillingWebhookController;
use Illuminate\Support\Facades\Route;

// Signature-verified gateway callback; never behind token auth.
Route::post('/webhooks/billing', BillingWebhookController::class)->name('webhooks.billing');
