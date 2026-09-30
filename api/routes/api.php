<?php

declare(strict_types=1);

use App\Http\Controllers\Console\AgentDeskController;
use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\CampaignController;
use App\Http\Controllers\Console\CustomerController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\NasDeviceController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\PlatformInvoiceController;
use App\Http\Controllers\Console\PlatformTenantController;
use App\Http\Controllers\Console\ReportController;
use App\Http\Controllers\Console\SessionController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SiteController;
use App\Http\Controllers\Console\StaffController;
use App\Http\Controllers\Console\TwoFactorController;
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
        Route::post('auth/password', [AuthController::class, 'changePassword'])->name('auth.password');
        Route::post('auth/two-factor', [TwoFactorController::class, 'start'])->name('auth.two-factor.start');
        Route::post('auth/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('auth.two-factor.confirm');
        Route::delete('auth/two-factor', [TwoFactorController::class, 'destroy'])->name('auth.two-factor.destroy');

        Route::get('voucher-batches', [VoucherBatchController::class, 'index'])->name('voucher-batches.index');
        Route::get('voucher-batches/{batch}', [VoucherBatchController::class, 'show'])->name('voucher-batches.show');
        Route::get('voucher-batches/{batch}/vouchers', [VoucherBatchController::class, 'vouchers'])->name('voucher-batches.vouchers');

        Route::get('agent/desk', AgentDeskController::class)->name('agent.desk');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('reports/collections', [ReportController::class, 'collections'])->name('reports.collections');
        Route::post('sessions/disconnect', [SessionController::class, 'disconnect'])->name('sessions.disconnect');

        Route::middleware('role:owner')->group(function (): void {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
            Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');

            Route::get('agents', [StaffController::class, 'agents'])->name('agents.index');
            Route::post('agents', [StaffController::class, 'storeAgent'])->name('agents.store');
            Route::patch('agents/{agent}', [StaffController::class, 'updateAgent'])->name('agents.update');

            Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
            Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
            Route::get('sites/{site}', [SiteController::class, 'show'])->name('sites.show');
            Route::patch('sites/{site}', [SiteController::class, 'update'])->name('sites.update');

            Route::get('nas-devices', [NasDeviceController::class, 'index'])->name('nas-devices.index');
            Route::post('nas-devices', [NasDeviceController::class, 'store'])->name('nas-devices.store');
            Route::patch('nas-devices/{nas_device}', [NasDeviceController::class, 'update'])->name('nas-devices.update');
            Route::get('nas-devices/{nas_device}/snippet', [NasDeviceController::class, 'snippet'])->name('nas-devices.snippet');

            Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('reports/orders', [ReportController::class, 'orders'])->name('reports.orders');
            Route::get('reports/insights', [ReportController::class, 'insights'])->name('reports.insights');

            Route::get('devices', [VoucherDeviceController::class, 'index'])->name('devices.index');
            Route::post('devices', [VoucherDeviceController::class, 'store'])->name('devices.store');
            Route::delete('devices/{voucher_device}', [VoucherDeviceController::class, 'destroy'])->name('devices.destroy');

            Route::apiResource('plans', PlanController::class);
            Route::post('plans/{plan}/offer', [PlanController::class, 'offer'])->name('plans.offer');
            Route::post('plans/{plan}/restore-price', [PlanController::class, 'restorePrice'])->name('plans.restore-price');

            Route::post('voucher-batches', [VoucherBatchController::class, 'store'])->name('voucher-batches.store');
            Route::patch('voucher-batches/{batch}', [VoucherBatchController::class, 'update'])->name('voucher-batches.update');
            Route::post('voucher-batches/{batch}/disable', [VoucherBatchController::class, 'disable'])->name('voucher-batches.disable');

            Route::apiResource('vouchers', VoucherController::class)->only(['index', 'show']);
            Route::get('vouchers/{voucher}/reveal', [VoucherController::class, 'reveal'])->name('vouchers.reveal');
            Route::post('vouchers/{voucher}/disable', [VoucherController::class, 'disable'])->name('vouchers.disable');

            Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo');
            Route::delete('settings/logo', [SettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');

            Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
            Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
            Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

            Route::post('customers/{customer}/reveal', [CustomerController::class, 'reveal'])->name('customers.reveal');

            Route::get('billing/invoices', [PlatformInvoiceController::class, 'index'])->name('billing.invoices');
            Route::get('billing/summary', [PlatformInvoiceController::class, 'summary'])->name('billing.summary');
        });

        Route::middleware('role:platform_admin')->group(function (): void {
            Route::get('platform/overview', [PlatformTenantController::class, 'overview'])->name('platform.overview');
            Route::get('platform/tenants', [PlatformTenantController::class, 'index'])->name('platform.tenants.index');
            Route::post('platform/tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
            Route::patch('platform/tenants/{tenant:uuid}', [PlatformTenantController::class, 'update'])->name('platform.tenants.update');
            Route::get('platform/invoices', [PlatformInvoiceController::class, 'index'])->name('platform.invoices.index');
            Route::post('platform/invoices', [PlatformInvoiceController::class, 'store'])->name('platform.invoices.store');
            Route::post('platform/invoices/{invoice}/paid', [PlatformInvoiceController::class, 'markPaid'])->name('platform.invoices.paid');
            Route::get('platform/payment-details', [PlatformInvoiceController::class, 'paymentDetails'])->name('platform.payment-details');
            Route::patch('platform/payment-details', [PlatformInvoiceController::class, 'updatePaymentDetails'])->name('platform.payment-details.update');
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
