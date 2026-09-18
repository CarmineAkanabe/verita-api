<?php

use App\Enums\CaseStatus;
use App\Events\CaseReadyForReview;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\CaseRecord;
use App\Models\Evidence;
use App\Services\AiProcessingService;
use App\Services\AuditLogService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fakeGeminiJson(array $overrides = []): array
{
    return array_merge([
        'summary' => 'A vendor payment allegedly not delivered as promised.',
        'timeline' => [['date' => '2026-08-01', 'event' => 'Payment sent']],
        'completeness' => ['Incident description provided'],
        'consistency' => ['Reported amount matches evidence'],
        'clarifications' => [],
    ], $overrides);
}

it('maps a valid Gemini response onto the case and fires the ready event', function () {
    Storage::fake('local');
    Event::fake([CaseReadyForReview::class]);

    $case = CaseRecord::factory()->create(['status' => CaseStatus::AI_PROCESSING]);
    Evidence::factory()->create(['case_record_id' => $case->id]);

    Http::fake(['*generateContent*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(fakeGeminiJson())]]]]],
    ])]);

    (new ProcessCaseWithAiJob($case))->handle(app(GeminiService::class), app(AuditLogService::class));

    $case->refresh();

    expect($case->status)->toBe(CaseStatus::AWAITING_REVIEW);
    expect($case->ai_summary)->not->toBeNull();
    expect($case->ai_findings)->toHaveKeys(['completeness', 'consistency', 'clarifications']);

    Event::assertDispatched(CaseReadyForReview::class);
});

it('throws (triggering retry) on a malformed Gemini response, with no partial save', function () {
    Storage::fake('local');

    $case = CaseRecord::factory()->create(['status' => CaseStatus::AI_PROCESSING]);
    Evidence::factory()->for($case, 'case')->create();

    Http::fake(['*generateContent*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(['summary' => 'only this key'])]]]]],
    ])]);

    expect(fn() => (new ProcessCaseWithAiJob($case))->handle(app(GeminiService::class), app(AuditLogService::class)))
        ->toThrow(Exception::class);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AI_PROCESSING);
});

it('reprocessing re-dispatches the same job', function () {
    Bus::fake();

    $case = CaseRecord::factory()->create(['status' => CaseStatus::AWAITING_REVIEW]);

    app(AiProcessingService::class)->dispatch($case);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AI_PROCESSING);
    Bus::assertDispatched(ProcessCaseWithAiJob::class);
});
