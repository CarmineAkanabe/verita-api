<?php

namespace App\Http\Controllers\V1;

use App\DTO\UpdateCaseStatusData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateCaseStatusRequest;
use App\Http\Resources\V1\CaseDetailResource;
use App\Models\CaseRecord;
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

    public function evidence(CaseRecord $case, string $evidence)
    {
        $this->authorize('view', $case);
        $file = $case->evidence()->findOrFail($evidence);
        return Storage::disk('local')->response($file->file_path);
    }
}
