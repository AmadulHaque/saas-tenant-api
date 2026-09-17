<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CompanyController;
use Illuminate\Support\Facades\Route;

Route::get('/company', [CompanyController::class, 'show'])->name('company.show');
Route::patch('/company', [CompanyController::class, 'update'])->name('company.update');
