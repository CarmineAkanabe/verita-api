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

Route::prefix('v1')->group(function () {

    Route::get('/ping', fn() => response()->json(['status' => 'ok']));

    /*
    |--------------------------------------------------------------------
    | Case Reporter — no User account, Case ID + Tracking PIN instead
    |--------------------------------------------------------------------
    */
    Route::middleware(['throttle:case-submit', 'idempotency'])
        ->post('cases', [CaseSubmissionController::class, 'store']);

    Route::middleware('throttle:pin-verify')
        ->post('cases/{caseId}/verify-pin', [CaseAuthController::class, 'verifyPin']);

    Route::middleware('auth:case-api')->prefix('cases/me')->group(function () {
        Route::get('/', [CaseDashboardController::class, 'show']);
        Route::post('/evidence', [CaseDashboardController::class, 'addEvidence']);
        Route::get('/evidence/{evidence}', [EvidenceDownloadController::class, 'show'])
            ->name('cases.me.evidence.show');
        Route::get('/messages', [CaseReporterMessageController::class, 'index']);
        Route::post('/messages', [CaseReporterMessageController::class, 'store']);
        Route::post('/escalate', [CaseReporterEscalationController::class, 'escalate']);
    });

    /*
    |--------------------------------------------------------------------
    | Staff auth — any authenticated Department Head or Manager
    |--------------------------------------------------------------------
    */
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('account/profile', [AccountController::class, 'updateProfile']);
        Route::get('account/dashboard', [AccountController::class, 'dashboard']);

        // View In-App Notifications — any staff member, own records only
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('audit-logs', [CaseManagementController::class, 'allAuditLogs']);
        Route::patch('notifications/{notification}', [NotificationController::class, 'markAsRead']);
    });

    /*
    |--------------------------------------------------------------------
    | Manager-only
    |--------------------------------------------------------------------
    */
    Route::middleware(['auth:api', 'role:MANAGER'])->group(function () {
        Route::prefix('departments')->group(function () {
            Route::get('/', [DepartmentController::class, 'index']);
            Route::post('/', [DepartmentController::class, 'store']);
            Route::put('/{department}', [DepartmentController::class, 'update']);
            Route::delete('/{department}', [DepartmentController::class, 'destroy']);
        });

        Route::apiResource('department-heads', DepartmentHeadController::class)->except(['show']);

        Route::prefix('case-assignments')->group(function () {
            Route::get('/', [CaseAssignmentController::class, 'index']);
            Route::post('/{case}', [CaseAssignmentController::class, 'assign']);
        });

        Route::get('reports/user-engagement', UserEngagementReportController::class);
    });

    /*
    |--------------------------------------------------------------------
    | Department Head-only — actions only the assigned/claiming DH can do
    |--------------------------------------------------------------------
    */
    Route::middleware(['auth:api', 'role:DEPARTMENT_HEAD'])->prefix('cases')->group(function () {
        Route::post('/{case}/claim', [CaseManagementController::class, 'claim']);
        Route::patch('/{case}/status', [CaseManagementController::class, 'updateStatus']);
        Route::post('/{case}/messages', [MessageController::class, 'store']);
    });

    /*
    |--------------------------------------------------------------------
    | Case viewing — DH (assigned or queue-eligible) OR Manager
    | (escalated cases only — CaseRecordPolicy::view enforces which)
    |--------------------------------------------------------------------
    */
    Route::middleware(['auth:api', 'role:DEPARTMENT_HEAD,MANAGER'])->prefix('cases')->group(function () {
        Route::get('/', [CaseManagementController::class, 'index']);
        Route::get('/{case}', [CaseManagementController::class, 'show']);
        Route::get('/{case}/evidence/{evidence}', [CaseManagementController::class, 'evidence']);
        Route::get('/{case}/messages', [MessageController::class, 'index']);
        Route::get('/{case}/audit-logs', [CaseManagementController::class, 'auditLogs']);
    });
});
