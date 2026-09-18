<?php

namespace App\Jobs;

use App\Models\CaseRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCaseWithAiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(public CaseRecord $case) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Gemini integration lands in Phase 6 — this stub exists so Phase 5
        // has something real to dispatch and Bus::assertDispatched() against.
    }
}
