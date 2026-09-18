<?php

use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CaseSubmissionController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\DepartmentHeadController;
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

    // Department Management
    Route::middleware(['auth:api', 'role:MANAGER'])->prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index']);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::put('/{department}', [DepartmentController::class, 'update']);
        Route::delete('/{department}', [DepartmentController::class, 'destroy']);
    });

    //Manager Features
    Route::middleware(['auth:api', 'role:MANAGER'])->group(function () {
        // User Management
        Route::apiResource('department-heads', DepartmentHeadController::class)->except(['show']);
    });

    // Case Submission (Anonymous, requires no auth)
    Route::middleware(['throttle:case-submit', 'idempotency'])
        ->post('cases', [CaseSubmissionController::class, 'store']);
});
