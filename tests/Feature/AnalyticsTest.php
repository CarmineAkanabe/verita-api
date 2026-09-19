<?php

use App\Enums\CaseCategory;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-managers from viewing the engagement report', function () {
    $head = User::factory()->departmentHead()->create();

    $this->actingAs($head, 'api')
        ->getJson('/api/v1/reports/user-engagement')
        ->assertForbidden();
});

it('returns case volume per department and average resolution time', function () {
    $manager = User::factory()->manager()->create();
    $deptA = Department::factory()->create(['name' => 'Software Engineering']);
    $deptB = Department::factory()->create(['name' => 'Graphics']);

    CaseRecord::factory()->count(3)->create(['department_id' => $deptA->id]);
    CaseRecord::factory()->count(2)->create(['department_id' => $deptB->id]);

    CaseRecord::factory()->create([
        'department_id' => $deptA->id,
        'created_at' => now()->subDays(2),
        'resolved_at' => now(),
    ]);
    CaseRecord::factory()->create([
        'department_id' => $deptA->id,
        'created_at' => now()->subDays(4),
        'resolved_at' => now(),
    ]);

    $response = $this->actingAs($manager, 'api')
        ->getJson('/api/v1/reports/user-engagement')
        ->assertOk();

    $response->assertJsonPath('data.averageResolutionDays', 3.0);

    $volumes = collect($response->json('data.caseVolumeByDepartment'));
    expect($volumes->firstWhere('department', 'Software Engineering')['count'])->toBe(5);
    expect($volumes->firstWhere('department', 'Graphics')['count'])->toBe(2);
});

it('returns null average resolution time when nothing is resolved yet', function () {
    $manager = User::factory()->manager()->create();
    CaseRecord::factory()->count(2)->create(['resolved_at' => null]);

    $this->actingAs($manager, 'api')
        ->getJson('/api/v1/reports/user-engagement')
        ->assertOk()
        ->assertJsonPath('data.averageResolutionDays', null);
});

it('breaks down cases by category and month', function () {
    $manager = User::factory()->manager()->create();
    $department = Department::factory()->create();

    CaseRecord::factory()->count(2)->create([
        'department_id' => $department->id,
        'category' => CaseCategory::FRAUD,
        'created_at' => '2026-01-15',
    ]);
    CaseRecord::factory()->create([
        'department_id' => $department->id,
        'category' => CaseCategory::FRAUD,
        'created_at' => '2026-02-05',
    ]);

    $response = $this->actingAs($manager, 'api')
        ->getJson('/api/v1/reports/user-engagement')
        ->assertOk();

    $breakdown = collect($response->json('data.categoryBreakdownOverTime'));
    expect($breakdown->firstWhere('month', '2026-01')['count'])->toBe(2);
    expect($breakdown->firstWhere('month', '2026-02')['count'])->toBe(1);
});
