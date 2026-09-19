<?php

use App\Enums\SenderType;
use App\Events\MessageSent;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

it('lets an assigned Department Head send a message', function () {
    Event::fake([MessageSent::class]);
    $dept = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['department_id' => $dept->id, 'assigned_to' => $dh->id]);

    $this->actingAs($dh, 'api')
        ->postJson("/api/v1/cases/{$case->id}/messages", ['content' => 'Can you clarify the date?'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, fn($e) => $e->message->sender_type === SenderType::DEPARTMENT_HEAD);
});

it('lets the Case Reporter send a message via their case-api token', function () {
    Event::fake([MessageSent::class]);
    $case = CaseRecord::factory()->create();
    $token = JWTAuth::fromUser($case); // same minting fix as Phase 7 — never auth('case-api')->login()

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/cases/me/messages', ['content' => 'Sure, it was the 3rd 🙂'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, fn($e) => $e->message->sender_type === SenderType::CASE_REPORTER);
});

it('authorizes broadcast auth for the assigned Department Head', function () {
    $dept = Department::factory()->create();
    $dh = User::factory()->departmentHead()->create(['department_id' => $dept->id]);
    $case = CaseRecord::factory()->create(['department_id' => $dept->id, 'assigned_to' => $dh->id]);

    $this->actingAs($dh, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-case.{$case->id}",
            'socket_id' => '12345.12345',
        ])
        ->assertOk();
});

it('authorizes broadcast auth for a case-api token on its own case', function () {
    $case = CaseRecord::factory()->create();
    $token = JWTAuth::fromUser($case);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-case.{$case->id}",
            'socket_id' => '12345.12345',
        ])
        ->assertOk();
});

it('rejects a case-api token for a different case on that channel', function () {
    $caseA = CaseRecord::factory()->create();
    $caseB = CaseRecord::factory()->create();
    $token = JWTAuth::fromUser($caseA);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/broadcasting/auth', ['channel_name' => "private-case.{$caseB->id}"])
        ->assertForbidden();
});
