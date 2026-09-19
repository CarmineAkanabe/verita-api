<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\EscalationService;
use Illuminate\Http\Request;

class CaseReporterEscalationController extends Controller
{
    public function __construct(private readonly EscalationService $service) {}

    public function escalate(Request $request)
    {
        $case = $this->service->escalate($request->user());
        return response()->json(['escalatedAt' => $case->escalated_at]);
    }
}
