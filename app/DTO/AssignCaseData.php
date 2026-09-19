<?php

namespace App\DTO;

readonly class AssignCaseData
{
    private function __construct(public string $departmentHeadId) {}

    public static function fromRequest(array $validated): self
    {
        return new self(departmentHeadId: $validated['departmentHeadId']);
    }
}
