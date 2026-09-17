<?php

namespace App\Services;

use App\DTO\LoginData;
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

        return ['token' => $token, 'user' => auth('api')->user()];
    }

    public function logout(): void
    {
        auth('api')->logout();
    }
}
