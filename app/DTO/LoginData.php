<?php

namespace App\DTO;

use App\Http\Requests\V1\LoginRequest;

final readonly class LoginData
{
    /**
     * Create a new class instance.
     */
    public function __construct(public string $email, public string $password) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self($request->validated('email'), $request->validated('password'));
    }
}
