<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('forbids a non-manager from creating a department head', function () {
    $departmentHead = User::factory()->departmentHead()->create();
    $department = Department::factory()->create();

    $this->actingAs($departmentHead, 'api')
        ->postJson('/api/v1/department-heads', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'departmentId' => $department->id,
        ])
        ->assertForbidden();
});

it('lets a manager create a department head', function () {
    $manager = User::factory()->manager()->create();
    $department = Department::factory()->create();

    $this->actingAs($manager, 'api')
        ->postJson('/api/v1/department-heads', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane@example.com',
            'departmentId' => $department->id,
            'password' => 'password123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'role' => Role::DEPARTMENT_HEAD->value,
    ]);
});

it('rejects creating a department head against a nonexistent department', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager, 'api')
        ->postJson('/api/v1/department-heads', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'departmentId' => (string) Str::uuid(),
        ])
        ->assertStatus(422);
});

it('lets a manager update a department head', function () {
    $manager = User::factory()->manager()->create();
    $departmentHead = User::factory()->departmentHead()->create();

    $this->actingAs($manager, 'api')
        ->putJson("/api/v1/department-heads/{$departmentHead->id}", [
            'firstName' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.firstName', 'Updated');
});

it('lets a manager delete a department head', function () {
    $manager = User::factory()->manager()->create();
    $departmentHead = User::factory()->departmentHead()->create();

    $this->actingAs($manager, 'api')
        ->deleteJson("/api/v1/department-heads/{$departmentHead->id}")
        ->assertNoContent();

    $this->assertModelMissing($departmentHead);
});
