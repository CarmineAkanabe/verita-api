<?php

use App\Enums\NotificationStatus;
use App\Enums\PresenceStatus;
use App\Enums\SenderType;
use App\Events\{CaseAssigned, CaseEscalated, CaseReadyForReview, CaseResolved, MessageSent};
use App\Mail\{CaseAssignedMail, CaseOutcomeMail, CaseReadyForReviewMail, NewMessageMail};
use App\Models\{CaseRecord, Department, Message, Notification, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn() => config([
    'queue.default' => 'sync',
    'broadcasting.default' => 'null',
]));

it('notifies every department head in the department when a case is ready for review', function () {
    Mail::fake();
    $department = Department::factory()->create();
    $heads = User::factory()->count(2)->departmentHead()->create(['department_id' => $department->id]);
    $case = CaseRecord::factory()->create(['department_id' => $department->id]);

    event(new CaseReadyForReview($case));

    foreach ($heads as $head) {
        expect(Notification::where('user_id', $head->id)->count())->toBe(1);
    }
    Mail::assertQueued(CaseReadyForReviewMail::class, 2);
});

it('notifies the assigned department head on assignment', function () {
    Mail::fake();
    $head = User::factory()->departmentHead()->create();
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);

    event(new CaseAssigned($case)); // adjust args to your actual constructor

    expect(Notification::where('user_id', $head->id)->count())->toBe(1);
    Mail::assertQueued(CaseAssignedMail::class, 1);
});

it('notifies the assigned department head on a new message only while offline', function () {
    Mail::fake();
    $head = User::factory()->departmentHead()->create(['presence_status' => PresenceStatus::OFFLINE]);
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);
    $message = Message::factory()->create([
        'case_record_id' => $case->id,
        'sender_type' => SenderType::CASE_REPORTER,
    ]);

    event(new MessageSent($message));

    expect(Notification::where('user_id', $head->id)->count())->toBe(1);
    Mail::assertQueued(NewMessageMail::class, 1);
});

it('does not notify the department head on a new message while online', function () {
    Mail::fake();
    $head = User::factory()->departmentHead()->create(['presence_status' => PresenceStatus::ONLINE]);
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);
    $message = Message::factory()->create([
        'case_record_id' => $case->id,
        'sender_type' => SenderType::CASE_REPORTER,
    ]);

    event(new MessageSent($message));

    expect(Notification::where('user_id', $head->id)->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('does not notify anyone when the department head sends the message', function () {
    Mail::fake();
    $head = User::factory()->departmentHead()->create(['presence_status' => PresenceStatus::OFFLINE]);
    $case = CaseRecord::factory()->create(['assigned_to' => $head->id]);
    $message = Message::factory()->create([
        'case_record_id' => $case->id,
        'sender_type' => SenderType::DEPARTMENT_HEAD,
    ]);

    event(new MessageSent($message));

    expect(Notification::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('notifies every manager when a case is resolved', function () {
    Mail::fake();
    $managers = User::factory()->count(2)->manager()->create();
    $case = CaseRecord::factory()->create();

    event(new CaseResolved($case));

    foreach ($managers as $manager) {
        expect(Notification::where('user_id', $manager->id)->count())->toBe(1);
    }
    Mail::assertQueued(CaseOutcomeMail::class, 2);
});

it('notifies every manager when a case is escalated', function () {
    Mail::fake();
    $managers = User::factory()->count(2)->manager()->create();
    $case = CaseRecord::factory()->create();

    event(new CaseEscalated($case));

    foreach ($managers as $manager) {
        expect(Notification::where('user_id', $manager->id)->count())->toBe(1);
    }
    Mail::assertQueued(CaseOutcomeMail::class, 2);
});

it('lists only the authenticated users own notifications', function () {
    $user = User::factory()->departmentHead()->create();
    $other = User::factory()->departmentHead()->create();
    Notification::factory()->for($user)->count(2)->create();
    Notification::factory()->for($other)->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('marks a notification as read', function () {
    $user = User::factory()->departmentHead()->create();
    $notification = Notification::factory()->for($user)->create(['status' => NotificationStatus::UNREAD]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/v1/notifications/{$notification->id}")
        ->assertNoContent();

    expect($notification->fresh()->status)->toBe(NotificationStatus::READ);
});

it('forbids marking someone elses notification as read', function () {
    $user = User::factory()->departmentHead()->create();
    $other = User::factory()->departmentHead()->create();
    $notification = Notification::factory()->for($other)->create();

    $this->actingAs($user, 'api')
        ->patchJson("/api/v1/notifications/{$notification->id}")
        ->assertForbidden();
});
