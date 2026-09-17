<?php

namespace App\Http\Controllers\V1;

use App\DTO\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

// use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(LoginData::fromRequest($request));

        return response()->json(['token' => $result['token'], 'user' => new UserResource($result['user'])]);
    }

    public function logout(): JsonResponse
    {
        $this->service->logout();
        return response()->json(status: 204);
    }
}
