<?php

namespace App\DTO;

use App\Http\Requests\V1\StoreDepartmentHeadRequest;
use App\Http\Requests\V1\UpdateDepartmentHeadRequest;

class DepartmentHeadData
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $password,
        public ?string $departmentId,
    ) {}

    public static function fromStoreRequest(StoreDepartmentHeadRequest $request): self
    {
        return new self(
            firstName: $request->validated('firstName'),
            lastName: $request->validated('lastName'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            departmentId: $request->validated('departmentId'),
        );
    }

    // All fields nullable here on purpose: 'sometimes' rules mean any of these
    // may be absent from the request. The Service filters nulls before the write.
    public static function fromUpdateRequest(UpdateDepartmentHeadRequest $request): self
    {
        return new self(
            firstName: $request->validated('firstName'),
            lastName: $request->validated('lastName'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            departmentId: $request->validated('departmentId'),
        );
    }
}
