<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 1. Crucial: Add this so Pest resets your test database for every run!
uses(RefreshDatabase::class);

it('updates profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->putJson('/api/v1/account/profile', ['first_name' => 'Nkolo'])
        // ->dump()
        ->assertOk()
        ->assertJsonPath('data.firstName', 'Nkolo');
});

it('returns department head dashboard shape', function () {
    $dept = Department::factory()->create();
    $head = User::factory()->create(['role' => Role::DEPARTMENT_HEAD, 'department_id' => $dept->id]);

    $this->actingAs($head, 'api')
        ->getJson('/api/v1/account/dashboard')
        ->assertOk()
        ->assertJsonStructure(['role', 'department', 'assignedCaseCount']);
});

it('returns manager dashboard shape', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager, 'api')
        ->getJson('/api/v1/account/dashboard')
        ->assertOk()
        ->assertJsonStructure(['role', 'departmentCount', 'userCount']);
});
