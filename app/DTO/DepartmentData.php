<?php

namespace App\DTO;

use App\Http\Requests\V1\StoreDepartmentRequest;
use App\Http\Requests\V1\UpdateDepartmentRequest;

final class DepartmentData
{
    /**
     * Create a new class instance.
     */
    public function __construct(public string $name) {}

    public static function fromRequest(StoreDepartmentRequest|UpdateDepartmentRequest $request): self
    {
        return new self($request->validated('name'));
    }
}
