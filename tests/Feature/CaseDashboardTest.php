// tests/Feature/CaseDashboardTest.php
<?php

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\CaseStatus;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\CaseRecord;
use App\Models\Evidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

it('verifies a correct Case ID + PIN and returns a token', function () {
    $case = CaseRecord::factory()->create(['tracking_pin_hash' => Hash::make('123456')]);

    $this->postJson("/api/v1/cases/{$case->id}/verify-pin", ['pin' => '123456'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']]);
});

it('returns the same 401 shape for a wrong Case ID as for a wrong PIN', function () {
    $case = CaseRecord::factory()->create(['tracking_pin_hash' => Hash::make('123456')]);

    $wrongCaseId = $this->postJson('/api/v1/cases/' . Str::uuid() . '/verify-pin', ['pin' => '123456']);
    $wrongPin = $this->postJson("/api/v1/cases/{$case->id}/verify-pin", ['pin' => '000000']);

    $wrongCaseId->assertStatus(401);
    $wrongPin->assertStatus(401);
    $json1 = $wrongCaseId->json();
    $json2 = $wrongPin->json();

    // Remove the URL paths since they dynamically differ by design
    unset($json1['instance'], $json2['instance']);

    expect($json1)->toBe($json2);
});

it('omits the AI Review section before AWAITING_REVIEW', function () {
    $case = CaseRecord::factory()->create(['status' => CaseStatus::AI_PROCESSING]);
    $token = JWTAuth::fromUser($case);

    $this->withToken($token)->getJson('/api/v1/cases/me')
        ->assertOk()
        ->assertJsonMissingPath('data.aiSummary');
});

it('includes the AI Review section once AWAITING_REVIEW', function () {
    $case = CaseRecord::factory()->create([
        'status' => CaseStatus::AWAITING_REVIEW,
        'ai_summary' => 'A summary.',
        'ai_findings' => ['completeness' => [], 'consistency' => [], 'clarifications' => []],
    ]);
    $token = JWTAuth::fromUser($case);

    $this->withToken($token)->getJson('/api/v1/cases/me')
        ->assertOk()
        ->assertJsonPath('data.aiSummary', 'A summary.');
});

it('adding evidence re-triggers AI reprocessing', function () {
    Storage::fake('local');
    Bus::fake();

    $case = CaseRecord::factory()->create(['status' => CaseStatus::AWAITING_REVIEW]);
    $token = JWTAuth::fromUser($case);

    $this->withToken($token)->postJson('/api/v1/cases/me/evidence', [
        'evidence' => [UploadedFile::fake()->image('followup.jpg')],
    ])->assertOk();

    Bus::assertDispatched(ProcessCaseWithAiJob::class);
    expect($case->refresh()->status)->toBe(CaseStatus::AI_PROCESSING);
});

it('rejects an evidence download for a case that does not own it', function () {
    Storage::fake('local');

    $ownCase = CaseRecord::factory()->create();
    $otherCase = CaseRecord::factory()->create();
    $otherEvidence = Evidence::factory()->create(['case_record_id' => $otherCase->id]);

    $token = JWTAuth::fromUser($ownCase);

    $this->withToken($token)->getJson("/api/v1/cases/me/evidence/{$otherEvidence->id}")
        ->assertStatus(404);
});

it('logs an audit entry when evidence is added', function () {
    Storage::fake('local');
    Bus::fake();

    $case = CaseRecord::factory()->create(['status' => CaseStatus::AWAITING_REVIEW]);
    $token = JWTAuth::fromUser($case);

    $this->withToken($token)->postJson('/api/v1/cases/me/evidence', [
        'evidence' => [UploadedFile::fake()->image('followup.jpg')],
    ])->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'case_record_id' => $case->id,
        'actor_type' => AuditActorType::SYSTEM->value,
        'action' => AuditAction::EVIDENCE_ADDED->value,
    ]);
});
