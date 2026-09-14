<?php

declare(strict_types=1);

use App\Http\Controllers\SnippeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('snippe/{tenantUuid}', SnippeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('snippe');
