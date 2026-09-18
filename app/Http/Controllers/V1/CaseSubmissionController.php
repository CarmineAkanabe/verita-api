<?php

namespace App\Http\Controllers\V1;

use App\DTO\CaseSubmissionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreCaseRequest;
use App\Services\CaseSubmissionService;
// use Illuminate\Http\Request;

class CaseSubmissionController extends Controller
{
    public function __construct(private readonly CaseSubmissionService $service) {}

    public function store(StoreCaseRequest $request)
    {
        $result = $this->service->submit(CaseSubmissionData::fromRequest($request));

        return response()->json([
            'data' => [
                'caseId' => $result['case']->id,
                'trackingPin' => $result['trackingPin'],
                'status' => $result['case']->status->value,
            ],
        ], 201);
    }
}
