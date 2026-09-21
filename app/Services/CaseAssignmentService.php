<?php

namespace App\Services;

use App\DTO\AssignCaseData;
use App\Enums\CaseStatus;
use App\Events\CaseAssigned;
use App\Models\CaseRecord;

class CaseAssignmentService
{
    public function assign(CaseRecord $case, AssignCaseData $data): CaseRecord
    {
        $case->assigned_to = $data->departmentHeadId;
        $case->save();

        CaseAssigned::dispatch($case);

        return $case;
    }

    public function awaitingAssignment()
    {
        return CaseRecord::query()
            ->whereNull('assigned_to')
            ->where('status', CaseStatus::AWAITING_REVIEW)
            ->orderBy('created_at')
            ->get();
    }
}
