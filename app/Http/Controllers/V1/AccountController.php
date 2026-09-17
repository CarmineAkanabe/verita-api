<?php

namespace App\Http\Controllers\V1;

use App\DTO\UpdateProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    public function __construct(private readonly AccountService $service) {}

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $this->service->updateProfile($request->user(), UpdateProfileData::fromRequest($request));
        return new UserResource($user);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->service->dashboard($request->user()));
    }
}
