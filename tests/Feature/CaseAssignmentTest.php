<?php

use App\Events\CaseAssigned;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('forbids a non-manager from assigning a case', function () {
    $dept = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $target = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['concerns_department_head' => true]);

    $this->actingAs($dh, 'api')
        ->postJson("/api/v1/case-assignments/{$case->id}", ['departmentHeadId' => $target->id])
        ->assertForbidden();
});

it('lets a Manager assign a conflict-of-interest case and fires the notification event', function () {
    Event::fake([CaseAssigned::class]);
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $target = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['concerns_department_head' => true, 'assigned_to' => null]);

    $this->actingAs($manager, 'api')
        ->postJson("/api/v1/case-assignments/{$case->id}", ['departmentHeadId' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.assignedTo', $target->id);

    Event::assertDispatched(CaseAssigned::class, fn($e) => $e->case->id === $case->id);
});

it('writes no AuditLog entry for assignment', function () {
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $target = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['concerns_department_head' => true]);

    $this->actingAs($manager, 'api')
        ->postJson("/api/v1/case-assignments/{$case->id}", ['departmentHeadId' => $target->id]);

    $this->assertDatabaseMissing('audit_logs', ['case_record_id' => $case->id]);
});

it('rejects a departmentHeadId that is not a real Department Head account', function () {
    $manager = User::factory()->manager()->create();
    $notADh = User::factory()->manager()->create();
    $case = CaseRecord::factory()->create(['concerns_department_head' => true]);

    $this->actingAs($manager, 'api')
        ->postJson("/api/v1/case-assignments/{$case->id}", ['departmentHeadId' => $notADh->id])
        ->assertStatus(422);
});

it('allows reassigning an already-assigned case to a different Department Head', function () {
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $original = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $replacement = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['assigned_to' => $original->id]);

    $this->actingAs($manager, 'api')
        ->postJson("/api/v1/case-assignments/{$case->id}", ['departmentHeadId' => $replacement->id])
        ->assertOk()
        ->assertJsonPath('data.assignedTo', $replacement->id);
});

it('lists only unassigned conflict-of-interest cases as awaiting assignment', function () {
    $manager = User::factory()->manager()->create();
    $alreadyAssignedDh = User::factory()->departmentHead()->create();

    $visible = CaseRecord::factory()->create(['concerns_department_head' => true, 'assigned_to' => null]);
    CaseRecord::factory()->create(['concerns_department_head' => false]);
    CaseRecord::factory()->create(['concerns_department_head' => true, 'assigned_to' => $alreadyAssignedDh->id]);

    $this->actingAs($manager, 'api')
        ->getJson('/api/v1/case-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});
