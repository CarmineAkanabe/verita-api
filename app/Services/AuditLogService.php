<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Models\AuditLog;
use App\Models\CaseRecord;

class AuditLogService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function log(
        CaseRecord $case,
        AuditActorType $actorType,
        AuditAction $action,
        ?string $previousValue,
        ?string $newValue,
        ?string $note = null
    ): void {
        AuditLog::create([
            'case_record_id' => $case->id,
            'actor_type' => $actorType,
            'action' => $action,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'note' => $note,
            'logged_at' => now(),
        ]);
    }
}
