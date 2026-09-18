<?php

namespace App\Jobs;

use App\DTO\AiCaseAnalysisData;
use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\CaseStatus;
use App\Events\CaseReadyForReview;
use App\Models\CaseRecord;
use App\Services\AuditLogService;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCaseWithAiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, Dispatchable;

    public int $tries = 3;
    /**
     * Create a new job instance.
     */
    public function __construct(public CaseRecord $case) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $gemini, AuditLogService $auditLog): void
    {
        $previousStatus = $this->case->status;

        $raw = $gemini->analyzeCase($this->case);
        $analysis = AiCaseAnalysisData::from($raw); // throws before anything touches the DB

        DB::transaction(function () use ($analysis, $previousStatus, $auditLog) {
            $this->case->update([
                'ai_summary' => $analysis->summary,
                'ai_timeline' => $analysis->timeline,
                'ai_findings' => [
                    'completeness' => $analysis->completeness,
                    'consistency' => $analysis->consistency,
                    'clarifications' => $analysis->clarifications,
                ],
                'status' => CaseStatus::AWAITING_REVIEW,
            ]);

            $auditLog->log(
                case: $this->case,
                actorType: AuditActorType::AI,
                action: AuditAction::AI_PROCESSED,
                previousValue: $previousStatus->value,
                newValue: CaseStatus::AWAITING_REVIEW->value,
            );
        });

        CaseReadyForReview::dispatch($this->case);
    }

    public function failed(?Throwable $exception): void
    {
        // Retries exhausted. No AuditLog write — AuditAction has no
        // failure-shaped value, wasn't going to invent one unasked. Case
        // just sits at AI_PROCESSING; recovery is AiProcessingService::dispatch(),
        // triggered manually, not automatically. Open flag, not a decision:
        // is a log line enough ops visibility before defense, or do you want
        // this surfaced somewhere a Manager actually sees it?
        report($exception);
    }
}
