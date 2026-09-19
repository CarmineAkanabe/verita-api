<?php

use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CaseAssignmentController;
use App\Http\Controllers\V1\CaseAuthController;
use App\Http\Controllers\V1\CaseDashboardController;
use App\Http\Controllers\V1\CaseManagementController;
use App\Http\Controllers\V1\CaseReporterMessageController;
use App\Http\Controllers\V1\CaseSubmissionController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\DepartmentHeadController;
use App\Http\Controllers\V1\EvidenceDownloadController;
use App\Http\Controllers\V1\MessageController;
use Illuminate\Support\Facades\Route;


/**
 * All Version 1 endpoints are here
 */
Route::prefix('v1')->group(function () {

    // Test if responsive
    Route::get('/ping', []);

    /**
     * Case Reporter Features
     */

    // Case Submission (Anonymous, requires no auth)
    Route::middleware(['throttle:case-submit', 'idempotency'])
        ->post('cases', [CaseSubmissionController::class, 'store']);

    // Pin Authentication
    Route::middleware('throttle:pin-verify')
        ->post('cases/{caseId}/verify-pin', [CaseAuthController::class, 'verifyPin']);

    // Case Reporter activities
    Route::middleware('auth:case-api')->group(function () {
        // Tracking Case
        Route::get('cases/me', [CaseDashboardController::class, 'show']);
        Route::post('cases/me/evidence', [CaseDashboardController::class, 'addEvidence']);
        Route::get('cases/me/evidence/{evidence}', [EvidenceDownloadController::class, 'show'])
            ->name('cases.me.evidence.show');
        // Messaging feature
        Route::middleware('auth:case-api')->prefix('cases/me')->group(function () {
            Route::get('/messages', [CaseReporterMessageController::class, 'index']);
            Route::post('/messages', [CaseReporterMessageController::class, 'store']);
        });
    });

    /**
     *  User Functionalities
     */

    // Authentication
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

    // Department Head Activities
    Route::middleware(['auth:api', 'role:DEPARTMENT_HEAD'])->prefix('cases')->group(function () {
        // Case Management
        Route::get('/', [CaseManagementController::class, 'index']);
        Route::get('/{case}', [CaseManagementController::class, 'show']);
        Route::post('/{case}/claim', [CaseManagementController::class, 'claim']);
        Route::patch('/{case}/status', [CaseManagementController::class, 'updateStatus']);
        Route::get('/{case}/evidence/{evidence}', [CaseManagementController::class, 'evidence']);

        // Messaging
        Route::get('/{case}/messages', [MessageController::class, 'index']);
        Route::post('/{case}/messages', [MessageController::class, 'store']);
    });

    // Assign Case System / Manager
    Route::middleware(['auth:api', 'role:MANAGER'])->prefix('case-assignments')->group(function () {
        Route::get('/', [CaseAssignmentController::class, 'index']);
        Route::post('/{case}', [CaseAssignmentController::class, 'assign']);
    });
});
