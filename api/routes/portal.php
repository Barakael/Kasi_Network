<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\BootstrapController;
use App\Http\Controllers\Portal\CheckoutController;
use App\Http\Controllers\Portal\DeviceController;
use App\Http\Controllers\Portal\IdentifyController;
use App\Http\Controllers\Portal\OrderController;
use App\Http\Controllers\Portal\RedeemController;
use App\Http\Controllers\Portal\TenantLogoController;
use Illuminate\Support\Facades\Route;

Route::post('bootstrap', BootstrapController::class)
    ->middleware('throttle:30,1')
    ->name('bootstrap');

Route::get('tenants/{tenant}/logo', TenantLogoController::class)
    ->middleware('throttle:60,1')
    ->name('tenants.logo');

Route::middleware(['portal', 'throttle:60,1'])->group(function (): void {
    Route::post('identify', IdentifyController::class)->name('identify');
    Route::post('redeem', RedeemController::class)->name('redeem');
    Route::post('checkout', CheckoutController::class)->name('checkout');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('devices/nearby', [DeviceController::class, 'nearby'])->name('devices.nearby');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
});

// USSD poll loops ~30/min; keep this outside the 60/min group so shared WAN IPs do not 429 mid-pay.
Route::get('orders/{order:uuid}', [OrderController::class, 'show'])
    ->middleware(['portal', 'throttle:120,1'])
    ->name('orders.show');
