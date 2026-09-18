<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-manager from creating a department', function () {
    $head = User::factory()->create(['role' => Role::DEPARTMENT_HEAD]);
    $this->actingAs($head, 'api')
        ->postJson('/api/v1/departments', ['name' => 'Finance'])
        ->assertStatus(403);
});

it('lets manager create a department', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager, 'api')
        ->postJson('/api/v1/departments', ['name' => 'Finance'])
        ->assertCreated()->assertJsonPath('data.name', 'Finance');
});

it('lets manager update a department', function () {
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $this->actingAs($manager, 'api')
        ->putJson("/api/v1/departments/{$dept->id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.name', 'Renamed');
});

it('lets manager delete a department', function () {
    $manager = User::factory()->manager()->create();
    $dept = Department::factory()->create();
    $this->actingAs($manager, 'api')
        ->deleteJson("/api/v1/departments/{$dept->id}")
        ->assertNoContent();
});

it('requires a name', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager, 'api')
        ->postJson('/api/v1/departments', [])
        ->assertStatus(422);
});
