<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\CaseRecord;

class AiProcessingService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function dispatch(CaseRecord $case): void
    {
        $case->update(['status' => CaseStatus::AI_PROCESSING]);

        ProcessCaseWithAiJob::dispatch($case);
    }
}
