<?php

namespace App\Http\Controllers\V1;

use App\DTO\SendMessageData;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SendMessageRequest;
use App\Http\Resources\V1\MessageResource;
use App\Services\MessageService;
use Illuminate\Http\Request;

class CaseReporterMessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    public function index(Request $request)
    {
        $case = $request->user(); // case-api guard resolves the CaseRecord itself
        $dh = $case->assignedTo;  // adjust to your actual relation name from Phase 1

        return response()->json([
            'departmentHead' => $dh ? [
                'name' => trim("{$dh->first_name} {$dh->last_name}"),
                'presenceStatus' => $dh->presence_status,
            ] : null,
            'messages' => MessageResource::collection(
                $case->messages()->orderBy('sent_at')->get()
            ),
        ]);
    }

    public function store(SendMessageRequest $request)
    {
        $case = $request->user();
        $message = $this->service->send($case, SenderType::CASE_REPORTER, SendMessageData::fromRequest($request->validated()));
        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
