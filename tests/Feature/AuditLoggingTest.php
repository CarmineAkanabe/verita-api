<?php

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\CaseStatus;
use App\Enums\SenderType;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Evidence;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

it('logs AI_PROCESSED with the correct status transition', function () {
    $case = CaseRecord::factory()->create(['status' => CaseStatus::AI_PROCESSING]);

    $this->mock(GeminiService::class, function ($mock) use ($case) {
        $mock->shouldReceive('analyzeCase')->once()->with($case)->andReturn([
            'summary' => 'Test summary',
            'timeline' => [],
            'completeness' => [],
            'consistency' => [],
            'clarifications' => [],
        ]);
    });

    (new ProcessCaseWithAiJob($case))->handle(app(GeminiService::class), app(AuditLogService::class));

    $log = AuditLog::where('case_record_id', $case->id)->where('action', AuditAction::AI_PROCESSED)->first();
    expect($log->previous_value)->toBe('AI_PROCESSING');
    expect($log->new_value)->toBe('AWAITING_REVIEW');
});

it('logs MESSAGE_SENT with the sender type as newValue', function () {
    $head = User::factory()->departmentHead()->create();
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);

    $this->actingAs($head, 'api')
        ->postJson("/api/v1/cases/{$case->id}/messages", ['content' => 'Can you clarify the amount?']);

    $log = AuditLog::where('case_record_id', $case->id)->where('action', AuditAction::MESSAGE_SENT)->first();
    expect($log->new_value)->toBe(SenderType::DEPARTMENT_HEAD->value);
    expect($log->previous_value)->toBeNull();
    expect($log->actor_type)->toBe(AuditActorType::DEPARTMENT_HEAD);
});

it('logs ESCALATED with the escalation timestamp as newValue', function () {
    $case = CaseRecord::factory()->create();
    $token = JWTAuth::fromUser($case);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/cases/me/escalate")
        ->assertOk();

    $log = AuditLog::where('case_record_id', $case->id)->where('action', AuditAction::ESCALATED)->first();
    expect($log->new_value)->not->toBeNull();
    expect($log->previous_value)->toBeNull();
});

it('logs EVIDENCE_REVIEWED when a department head views evidence', function () {
    Storage::fake('local');

    $head = User::factory()->departmentHead()->create();
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);

    $path = 'evidence/test-evidence.jpg';
    Storage::disk('local')->put($path, 'fake-file-contents');

    $evidence = Evidence::factory()->create([
        'case_record_id' => $case->id,
        'file_path' => $path,
    ]);

    $this->actingAs($head, 'api')
        ->get("/api/v1/cases/{$case->id}/evidence/{$evidence->id}")
        ->assertOk();

    $log = AuditLog::where('case_record_id', $case->id)->where('action', AuditAction::EVIDENCE_REVIEWED)->first();
    expect($log)->not->toBeNull();
    expect($log->new_value)->toBe((string) $evidence->id);
    expect($log->actor_type)->toBe(AuditActorType::DEPARTMENT_HEAD);
});

it('logs STATUS_CHANGED with old and new status on every transition', function () {
    $head = User::factory()->departmentHead()->create();
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id, 'status' => CaseStatus::UNDER_INVESTIGATION]);

    $this->actingAs($head, 'api')
        ->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => 'RESOLVED',
            'note' => 'Substantiated.',
            'resolutionSummary' => 'Substantiated.',
        ])
        ->assertOk();

    $log = AuditLog::where('case_record_id', $case->id)->where('action', AuditAction::STATUS_CHANGED)->first();
    expect($log->previous_value)->toBe('UNDER_INVESTIGATION');
    expect($log->new_value)->toBe('RESOLVED');
});
