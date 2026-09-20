<?php

use App\Enums\CaseStatus;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\CaseRecord;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
});

function validCasePayload(Department $department, array $overrides = []): array
{
    return array_merge([
        'departmentId' => $department->id,
        'description' => 'A vendor payment that never arrived as agreed.',
        'purposeOfTransaction' => 'Supplier invoice payment',
        'amountInvolved' => 150000,
        'personInvolved' => 'John Doe',
        'transactionDate' => '2026-08-01',
        'concernsDepartmentHead' => false,
        'evidence' => [UploadedFile::fake()->image('whatsapp.jpg')],
    ], $overrides);
}

it('submits a case and shows the tracking PIN once', function () {
    $department = Department::factory()->create();

    $response = $this->postJson('/api/v1/cases', validCasePayload($department), [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['data' => ['caseId', 'trackingPin', 'status']]);
    expect($response->json('data.status'))->toBe(CaseStatus::AI_PROCESSING->value);

    Bus::assertDispatched(ProcessCaseWithAiJob::class);
});

it('rejects submission with a missing required field', function () {
    $department = Department::factory()->create();
    $payload = validCasePayload($department);
    unset($payload['description']);

    $this->postJson('/api/v1/cases', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('rejects submission with no evidence', function () {
    $department = Department::factory()->create();
    $payload = validCasePayload($department, ['evidence' => []]);

    $this->postJson('/api/v1/cases', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('requires an Idempotency-Key header', function () {
    $department = Department::factory()->create();

    $this->postJson('/api/v1/cases', validCasePayload($department))
        ->assertStatus(400);
});

it('creates only one case when the same Idempotency-Key is replayed', function () {
    $department = Department::factory()->create(['id' => (string) Str::uuid()]);
    $key = (string) Str::uuid();
    $payload = validCasePayload($department);

    $this->postJson('/api/v1/cases', $payload, ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/cases', $payload, ['Idempotency-Key' => $key])->assertCreated();

    // dump(CaseRecord::where('department_id', $department->id)->get(['id', 'status'])->toArray());
    // Scope the count strictly to the department isolated in this specific test
    expect(CaseRecord::where('department_id', $department->id)->count())->toBe(1);
});

it('stores the conflict-of-interest flag for later queue filtering', function () {
    $department = Department::factory()->create();
    $payload = validCasePayload($department, ['concernsDepartmentHead' => true]);

    $this->postJson('/api/v1/cases', $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $this->assertDatabaseHas('case_records', [
        'department_id' => $department->id,
        'concerns_department_head' => true,
    ]);
});
