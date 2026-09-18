<?php

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Events\CaseResolved;
use App\Exceptions\CaseAlreadyClaimedException;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\User;
use App\Services\CaseManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function claimedCase(): array
{
    $dept = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create([
        'department_id' => $dept->id,
        'status' => CaseStatus::UNDER_INVESTIGATION,
        'assigned_to' => $dh->id,
    ]);
    return [$dh, $case];
}

it('scopes the queue by department and excludes conflict-of-interest cases', function () {
    $dept = Department::factory()->create();
    $other = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);

    $visible = CaseRecord::factory()->create(['department_id' => $dept->id, 'status' => CaseStatus::AWAITING_REVIEW, 'concerns_department_head' => false]);
    CaseRecord::factory()->create(['department_id' => $dept->id, 'status' => CaseStatus::AWAITING_REVIEW, 'concerns_department_head' => true]);
    CaseRecord::factory()->create(['department_id' => $other->id, 'status' => CaseStatus::AWAITING_REVIEW]);

    $this->actingAs($dh, 'api')->getJson('/api/v1/cases')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
});

it('only lets one of two simultaneous claims succeed', function () {
    $dept = Department::factory()->create();
    $dh1 = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $dh2 = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['department_id' => $dept->id, 'status' => CaseStatus::AWAITING_REVIEW, 'concerns_department_head' => false]);

    $first = $this->actingAs($dh1, 'api')->postJson("/api/v1/cases/{$case->id}/claim");
    $second = $this->actingAs($dh2, 'api')->postJson("/api/v1/cases/{$case->id}/claim");

    expect([$first->status(), $second->status()])->toContain(200)->toContain(403);
});

it('requires a note on every status update', function () {
    [$dh, $case] = claimedCase();
    $this->actingAs($dh, 'api')
        ->patchJson("/api/v1/cases/{$case->id}/status", ['status' => CaseStatus::RESOLVED->value, 'resolutionSummary' => 'x'])
        ->assertStatus(422)->assertJsonValidationErrors('note');
});

it('writes an audit log on every status transition', function () {
    [$dh, $case] = claimedCase();
    $this->actingAs($dh, 'api')->patchJson("/api/v1/cases/{$case->id}/status", [
        'status' => CaseStatus::DISMISSED->value,
        'note' => 'insufficient evidence',
        'resolutionSummary' => 'Dismissed',
    ])->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'case_record_id' => $case->id,
        'action' => AuditAction::STATUS_CHANGED->value,
        'new_value' => CaseStatus::DISMISSED->value,
    ]);
});

it('fires CaseResolved only when the new status is resolved', function () {
    Event::fake([CaseResolved::class]);

    [$dh, $case] = claimedCase();
    $this->actingAs($dh, 'api')->patchJson("/api/v1/cases/{$case->id}/status", [
        'status' => CaseStatus::DISMISSED->value,
        'note' => 'n/a',
        'resolutionSummary' => 'n/a',
    ]);
    Event::assertNotDispatched(CaseResolved::class);

    [$dh2, $case2] = claimedCase();
    $this->actingAs($dh2, 'api')->patchJson("/api/v1/cases/{$case2->id}/status", [
        'status' => CaseStatus::RESOLVED->value,
        'note' => 'substantiated',
        'resolutionSummary' => 'Fraud substantiated',
    ]);
    Event::assertDispatched(CaseResolved::class);
});

// (b) the actual race, proven at the Service/DB layer, Policy bypassed on purpose
it('the atomic claim update only lets one of two stale instances win', function () {
    $dept = Department::factory()->create();
    $dh1 = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $dh2 = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create([
        'department_id' => $dept->id,
        'status' => CaseStatus::AWAITING_REVIEW,
        'concerns_department_head' => false,
    ]);

    $staleA = CaseRecord::find($case->id);
    $staleB = CaseRecord::find($case->id); // both fetched before either write — simulates the overlap

    $service = app(CaseManagementService::class);
    $service->claim($staleA, $dh1);

    expect(fn() => $service->claim($staleB, $dh2))
        ->toThrow(CaseAlreadyClaimedException::class);
});
