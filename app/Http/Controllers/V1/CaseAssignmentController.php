<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\DTO\AssignCaseData;
use App\Http\Requests\V1\AssignCaseRequest;
use App\Http\Resources\V1\CaseDetailResource;
use App\Models\CaseRecord;
use App\Services\CaseAssignmentService;

class CaseAssignmentController extends Controller
{
    public function __construct(private readonly CaseAssignmentService $service) {}

    public function index()
    {
        return CaseDetailResource::collection($this->service->awaitingAssignment());
    }

    public function assign(AssignCaseRequest $request, CaseRecord $case)
    {
        $data = AssignCaseData::fromRequest($request->validated());
        return new CaseDetailResource($this->service->assign($case, $data));
    }
}
