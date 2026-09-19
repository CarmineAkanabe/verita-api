<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Events\CaseEscalated;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function escalate(CaseRecord $case): CaseRecord
    {
        $case = DB::transaction(function () use ($case) {
            $case->escalated_at = now();
            $case->save();

            $this->auditLog->log(
                case: $case,
                actorType: AuditActorType::SYSTEM,
                action: AuditAction::ESCALATED,
                previousValue: null,
                newValue: 'ESCALATED',
            );

            return $case;
        });

        CaseEscalated::dispatch($case); // Phase 12 attaches the Manager-notifying Listener

        return $case;
    }
}
