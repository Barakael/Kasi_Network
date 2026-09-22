<?php

use App\Http\Controllers\Portal\CaptivePortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/captive-portal', CaptivePortalController::class);
