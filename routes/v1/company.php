<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Company\ShowController;
use App\Http\Controllers\Api\V1\Company\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/company', ShowController::class)->name('company.show');
Route::patch('/company', UpdateController::class)->name('company.update');
