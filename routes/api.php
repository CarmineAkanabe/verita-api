<?php

use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /**
     * All Version 1 endpoints are here
     */
    // Test if responsive
    Route::post('/ping', []);

    // Authentication(Login)
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Authentication (Account Update and user info)
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('account/profile', [AccountController::class, 'updateProfile']);
        Route::get('account/dashboard', [AccountController::class, 'dashboard']);
    });
});
