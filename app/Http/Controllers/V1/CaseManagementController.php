<?php

namespace App\Http\Controllers\V1;

use App\DTO\UpdateCaseStatusData;
use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateCaseStatusRequest;
use App\Http\Resources\V1\AuditLogResource;
use App\Http\Resources\V1\CaseDetailResource;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Services\AuditLogService;
use App\Services\CaseManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CaseManagementController extends Controller
{
    public function __construct(private readonly CaseManagementService $service) {}

    public function index(Request $request)
    {
        return CaseDetailResource::collection($this->service->queueFor($request->user()));
    }

    public function show(CaseRecord $case)
    {
        $this->authorize('view', $case);
        return new CaseDetailResource($case->load('evidence'));
    }

    public function claim(CaseRecord $case)
    {
        $this->authorize('claim', $case);
        return new CaseDetailResource($this->service->claim($case, auth('api')->user()));
    }

    public function updateStatus(UpdateCaseStatusRequest $request, CaseRecord $case)
    {
        $this->authorize('updateStatus', $case);
        $data = UpdateCaseStatusData::fromRequest($request->validated());
        return new CaseDetailResource($this->service->updateStatus($case, $data));
    }

    public function evidence(CaseRecord $case, string $evidence, AuditLogService $auditLog)
    {
        $this->authorize('view', $case);
        $file = $case->evidence()->findOrFail($evidence);

        $auditLog->log(
            case: $case,
            actorType: auth('api')->user()->role === Role::DEPARTMENT_HEAD
                ? AuditActorType::DEPARTMENT_HEAD
                : AuditActorType::SYSTEM,
            action: AuditAction::EVIDENCE_REVIEWED,
            previousValue: null,
            newValue: (string) $file->id,
        );

        return Storage::disk('local')->response($file->file_path);
    }

    public function auditLogs(CaseRecord $case)
    {
        $this->authorize('view', $case);

        $logs = $case->auditLogs()
            ->orderBy('logged_at', 'desc')
            ->get();

        return AuditLogResource::collection($logs);
    }

    public function allAuditLogs(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user->role !== Role::MANAGER) {
            $caseIds = CaseRecord::query()
                ->where('assigned_to', $user->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('department_id', $user->department_id)
                        ->where('concerns_department_head', false);
                })
                ->pluck('id');
            $query->whereIn('case_record_id', $caseIds);
        }

        $logs = $query->orderBy('logged_at', 'desc')->take(100)->get();
        return AuditLogResource::collection($logs);
    }
}