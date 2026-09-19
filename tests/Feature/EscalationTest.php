<?php

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Events\CaseEscalated;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

it('lets the Case Reporter escalate, logging it and firing the event', function () {
    Event::fake([CaseEscalated::class]);
    $case = CaseRecord::factory()->create();
    $token = JWTAuth::fromUser($case);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/cases/me/escalate')
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'case_record_id' => $case->id,
        'action' => AuditAction::ESCALATED->value,
    ]);
    Event::assertDispatched(CaseEscalated::class, fn($e) => $e->case->id === $case->id);
});

it('grants the Manager access to an escalated case and not to an ordinary one', function () {
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);

    $escalated = CaseRecord::factory()->create(['department_id' => $dept->id, 'assigned_to' => $dh->id, 'escalated_at' => now()]);
    $ordinary = CaseRecord::factory()->create(['department_id' => $dept->id, 'assigned_to' => $dh->id]);

    $this->actingAs($manager, 'api')->getJson("/api/v1/cases/{$escalated->id}")->assertOk();
    $this->actingAs($manager, 'api')->getJson("/api/v1/cases/{$ordinary->id}")->assertForbidden();
});

it('grants the Manager access to an escalated case\'s chat log too', function () {
    $manager = User::factory()->manager()->create();
    $case = CaseRecord::factory()->create(['escalated_at' => now()]);

    $this->actingAs($manager, 'api')
        ->getJson("/api/v1/cases/{$case->id}/messages")
        ->assertOk();
});

it('never grants the Manager DH-only actions, even on an escalated case', function () {
    $manager = User::factory()->manager()->create();
    $case = CaseRecord::factory()->create(['escalated_at' => now(), 'status' => CaseStatus::AWAITING_REVIEW]);

    $this->actingAs($manager, 'api')
        ->postJson("/api/v1/cases/{$case->id}/claim")
        ->assertForbidden();
});
