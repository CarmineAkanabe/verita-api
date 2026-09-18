<?php

namespace App\Services;

use App\DTO\DepartmentData;
use App\Models\Department;

class DepartmentService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function create(DepartmentData $data): Department
    {
        return Department::create(['name' => $data->name]);
    }

    public function update(Department $department, DepartmentData $data): Department
    {
        $department->update(['name' => $data->name]);
        return $department;
    }

    public function delete(Department $department): void
    {
        $department->delete(); // DB restrictOnDelete (cases) will reject this if cases exist — no app-level check yet
    }
}
