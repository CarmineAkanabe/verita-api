<?php

namespace App\Http\Controllers\V1;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AddEvidenceRequest;
use App\Http\Resources\V1\CaseReporterDashboardResource;
use App\Services\AiProcessingService;
use App\Services\AuditLogService;
use App\Services\EvidenceService;
use Illuminate\Http\Request;

class CaseDashboardController extends Controller
{
    public function __construct(
        private readonly EvidenceService $evidence,
        private readonly AiProcessingService $aiProcessing,
        private readonly AuditLogService $auditLog,
    ) {}

    public function show(Request $request)
    {
        $case = $request->user('case-api');

        return new CaseReporterDashboardResource($case->load('evidence'));
    }

    public function addEvidence(AddEvidenceRequest $request)
    {
        $case = $request->user('case-api');
        $previousStatus = $case->status;

        $this->evidence->store($case, $request->file('evidence', []));
        $this->aiProcessing->dispatch($case);

        $this->auditLog->log(
            case: $case,
            actorType: AuditActorType::SYSTEM,
            action: AuditAction::EVIDENCE_ADDED,
            previousValue: $previousStatus->value,
            newValue: CaseStatus::AI_PROCESSING->value,
        );

        return new CaseReporterDashboardResource($case->refresh()->load('evidence'));
    }
}
