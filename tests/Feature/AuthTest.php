<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 1. Crucial: Add this so Pest resets your test database for every run!
uses(RefreshDatabase::class);

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertOk()->assertJsonStructure(['token', 'user']);
});

it('rejects wrong password', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(401);
});

it('rejects missing fields', function () {
    $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
});
