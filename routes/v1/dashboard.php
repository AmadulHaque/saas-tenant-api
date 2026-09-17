<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Dashboard\ShowController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowController::class)->name('dashboard.show');
