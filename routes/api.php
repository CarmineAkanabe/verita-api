<?php

use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CaseAssignmentController;
use App\Http\Controllers\V1\CaseAuthController;
use App\Http\Controllers\V1\CaseDashboardController;
use App\Http\Controllers\V1\CaseManagementController;
use App\Http\Controllers\V1\CaseReporterEscalationController;
use App\Http\Controllers\V1\CaseReporterMessageController;
use App\Http\Controllers\V1\CaseSubmissionController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\DepartmentHeadController;
use App\Http\Controllers\V1\EvidenceDownloadController;
use App\Http\Controllers\V1\MessageController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\UserEngagementReportController;
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
            Route::post('/escalate', [CaseReporterEscalationController::class, 'escalate']);
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

    // Department Head Activities — actions only the assigned/claiming DH can do
    Route::middleware(['auth:api', 'role:DEPARTMENT_HEAD'])->prefix('cases')->group(function () {
        Route::get('/', [CaseManagementController::class, 'index']);
        Route::post('/{case}/claim', [CaseManagementController::class, 'claim']);
        Route::patch('/{case}/status', [CaseManagementController::class, 'updateStatus']);
        Route::post('/{case}/messages', [MessageController::class, 'store']);
    });

    // Case Viewing — DH (assigned or queue-eligible) OR Manager (escalated cases only — CaseRecordPolicy::view enforces which)
    Route::middleware(['auth:api', 'role:DEPARTMENT_HEAD,MANAGER'])->prefix('cases')->group(function () {
        Route::get('/{case}', [CaseManagementController::class, 'show']);
        Route::get('/{case}/evidence/{evidence}', [CaseManagementController::class, 'evidence']);
        Route::get('/{case}/messages', [MessageController::class, 'index']);
    });

    // Assign Case System / Manager
    Route::middleware(['auth:api', 'role:MANAGER'])->prefix('case-assignments')->group(function () {
        Route::get('/', [CaseAssignmentController::class, 'index']);
        Route::post('/{case}', [CaseAssignmentController::class, 'assign']);
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}', [NotificationController::class, 'markAsRead']);

    // Manager User Engagement Report
    Route::middleware('role:MANAGER')->get('reports/user-engagement', UserEngagementReportController::class);
});
