<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Me\ShowController;
use Illuminate\Support\Facades\Route;

Route::get('/me', ShowController::class)->name('me.show');
