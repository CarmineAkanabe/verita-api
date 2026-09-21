<?php

namespace App\Services;

use App\DTO\LoginData;
use App\Enums\PresenceStatus;
use App\Enums\Role;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\JWTGuard;

final class AuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function login(LoginData $data): array
    {
        /**
         *  @var JWTGuard $token
         */
        $token = auth('api')->attempt(['email' => $data->email, 'password' => $data->password]);

        if (! $token) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $user = auth('api')->user();
        if ($user && $user->role === Role::DEPARTMENT_HEAD) {
            $user->presence_status = PresenceStatus::ONLINE;
            $user->save();
        }

        return ['token' => $token, 'user' => $user];
    }

    public function logout(): void
    {
        $user = auth('api')->user();
        if ($user && $user->role === Role::DEPARTMENT_HEAD) {
            $user->presence_status = PresenceStatus::OFFLINE;
            $user->save();
        }
        auth('api')->logout();
    }
}
