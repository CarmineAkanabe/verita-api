<?php

namespace App\Services;

use App\DTO\UpdateCaseStatusData;
use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Events\CaseResolved;
use App\Exceptions\CaseAlreadyClaimedException;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CaseManagementService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function queueFor(User $user)
    {
        if ($user->role === Role::MANAGER) {
            return CaseRecord::query()
                ->orderByDesc('created_at')
                ->get();
        }

        return CaseRecord::query()
            ->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('department_id', $user->department_id)
                            ->where('status', CaseStatus::AWAITING_REVIEW)
                            ->where('concerns_department_head', false)
                            ->whereNull('assigned_to');
                    });
            })
            ->orderBy('created_at')
            ->get();
    }

    public function claim(CaseRecord $case, User $departmentHead): CaseRecord
    {
        return DB::transaction(function () use ($case, $departmentHead) {
            $previous = $case->status->value;

            $affected = CaseRecord::query()
                ->where('id', $case->id)
                ->whereNull('assigned_to')
                ->update([
                    'assigned_to' => $departmentHead->id,
                    'status' => CaseStatus::UNDER_INVESTIGATION,
                ]);

            if ($affected === 0) {
                throw new CaseAlreadyClaimedException();
            }

            $case->refresh();

            $this->auditLog->log(
                case: $case,
                actorType: AuditActorType::DEPARTMENT_HEAD,
                action: AuditAction::STATUS_CHANGED,
                previousValue: $previous,
                newValue: CaseStatus::UNDER_INVESTIGATION->value,
            );

            return $case;
        });
    }

    public function updateStatus(CaseRecord $case, UpdateCaseStatusData $data): CaseRecord
    {
        $case = DB::transaction(function () use ($case, $data) {
            $previous = $case->status->value;
            $case->status = $data->status;

            if (in_array($data->status, [CaseStatus::RESOLVED, CaseStatus::DISMISSED], true)) {
                $case->resolution_summary = $data->resolutionSummary;
                $case->resolved_at = now();
            }

            $case->save();

            $this->auditLog->log(
                case: $case,
                actorType: AuditActorType::DEPARTMENT_HEAD,
                action: AuditAction::STATUS_CHANGED,
                previousValue: $previous,
                newValue: $data->status->value,
                note: $data->note,
            );

            return $case;
        });

        if ($data->status === CaseStatus::RESOLVED) {
            CaseResolved::dispatch($case); // fired after commit, matches Phase 6/9's rule
        }

        return $case;
    }
}
