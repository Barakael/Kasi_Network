<?php

declare(strict_types=1);

use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\NasDeviceController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\ReportController;
use App\Http\Controllers\Console\SessionController;
use App\Http\Controllers\Console\SiteController;
use App\Http\Controllers\Console\StaffController;
use App\Http\Controllers\Console\VoucherBatchController;
use App\Http\Controllers\Console\VoucherController;
use App\Http\Controllers\Console\VoucherDeviceController;
use App\Http\Controllers\Console\VoucherPrintController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Console API
|------------------------------------------------------------------------------
|
| Consumed by the operator console PWA and authenticated with Sanctum tokens.
| The captive portal has its own unauthenticated surface in routes/portal.php.
|
| Tenant scoping is applied by middleware for every route in this file, so
| controllers query models directly and the global scope narrows them to the
| signed-in user's operator.
|
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    // Deliberately throttled harder than the rest of the API: this is the only
    // endpoint where a guess is worth making.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('voucher-batches', [VoucherBatchController::class, 'index'])->name('voucher-batches.index');
        Route::get('voucher-batches/{batch}', [VoucherBatchController::class, 'show'])->name('voucher-batches.show');
        Route::get('voucher-batches/{batch}/vouchers', [VoucherBatchController::class, 'vouchers'])->name('voucher-batches.vouchers');

        Route::middleware('role:owner,staff')->group(function (): void {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
            Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::post('sessions/disconnect', [SessionController::class, 'disconnect'])->name('sessions.disconnect');

            Route::get('agents', [StaffController::class, 'agents'])->name('agents.index');

            Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
            Route::post('sites', [SiteController::class, 'store'])->name('sites.store');

            Route::get('nas-devices', [NasDeviceController::class, 'index'])->name('nas-devices.index');
            Route::post('nas-devices', [NasDeviceController::class, 'store'])->name('nas-devices.store');
            Route::patch('nas-devices/{nas_device}', [NasDeviceController::class, 'update'])->name('nas-devices.update');
            Route::get('nas-devices/{nas_device}/snippet', [NasDeviceController::class, 'snippet'])->name('nas-devices.snippet');

            Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('reports/orders', [ReportController::class, 'orders'])->name('reports.orders');

            Route::get('devices', [VoucherDeviceController::class, 'index'])->name('devices.index');
            Route::post('devices', [VoucherDeviceController::class, 'store'])->name('devices.store');
            Route::delete('devices/{voucher_device}', [VoucherDeviceController::class, 'destroy'])->name('devices.destroy');

            Route::apiResource('plans', PlanController::class);

            Route::post('voucher-batches', [VoucherBatchController::class, 'store'])->name('voucher-batches.store');
            Route::patch('voucher-batches/{batch}', [VoucherBatchController::class, 'update'])->name('voucher-batches.update');
            Route::post('voucher-batches/{batch}/disable', [VoucherBatchController::class, 'disable'])->name('voucher-batches.disable');

            Route::apiResource('vouchers', VoucherController::class)->only(['index', 'show']);
            Route::get('vouchers/{voucher}/reveal', [VoucherController::class, 'reveal'])->name('vouchers.reveal');
            Route::post('vouchers/{voucher}/disable', [VoucherController::class, 'disable'])->name('vouchers.disable');
        });
    });
});

/*
|------------------------------------------------------------------------------
| Printable output
|------------------------------------------------------------------------------
|
| Outside the /v1 JSON surface because it returns a document, not data: the
| operator opens it in a tab and prints it. Agents reach this and nothing else,
| which is the whole of the reseller tier.
|
*/

Route::middleware('auth:sanctum')
    ->get('print/voucher-batches/{batch}', VoucherPrintController::class)
    ->name('voucher-batches.print');
