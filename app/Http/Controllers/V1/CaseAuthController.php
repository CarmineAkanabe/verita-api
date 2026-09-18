<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\CaseAuthService;
use App\Http\Requests\V1\VerifyCasePinRequest;
// use Illuminate\Http\Request;

class CaseAuthController extends Controller
{
    public function __construct(private readonly CaseAuthService $service) {}

    public function verifyPin(VerifyCasePinRequest $request, string $caseId)
    {
        $token = $this->service->verifyPin($caseId, $request->validated('pin'));

        return response()->json(['data' => ['token' => $token]]);
    }
}
