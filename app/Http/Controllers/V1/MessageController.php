<?php

namespace App\Http\Controllers\V1;

use App\DTO\SendMessageData;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SendMessageRequest;
use App\Http\Resources\V1\MessageResource;
use App\Models\CaseRecord;
use App\Services\MessageService;
// use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    public function index(CaseRecord $case)
    {
        $this->authorize('view', $case);
        return response()->json([
            'messages' => MessageResource::collection(
                $case->messages()->orderBy('sent_at')->get()
            ),
        ]);
    }

    public function store(SendMessageRequest $request, CaseRecord $case)
    {
        $this->authorize('communicate', $case);
        $message = $this->service->send($case, SenderType::DEPARTMENT_HEAD, SendMessageData::fromRequest($request->validated()));
        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
