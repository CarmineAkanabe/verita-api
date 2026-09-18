<?php

namespace App\Services;

use App\DTO\DepartmentHeadData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DepartmentHeadService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function create(DepartmentHeadData $data): User
    {
        return DB::transaction(fn() => User::create([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'email' => $data->email,
            'department_id' => $data->departmentId,
            // 'hashed' cast on User (Phase 1) hashes this automatically — no Hash::make() call.
            'password' => $data->password,
            'role' => Role::DEPARTMENT_HEAD,
        ]));
    }

    public function update(User $departmentHead, DepartmentHeadData $data): User
    {
        return DB::transaction(function () use ($departmentHead, $data) {
            $departmentHead->fill(array_filter([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'email' => $data->email,
                'password' => $data->password,
                'department_id' => $data->departmentId,
            ], fn($value) => $value !== null));

            $departmentHead->save();

            return $departmentHead;
        });
    }

    public function delete(User $departmentHead): void
    {
        // No app-level guard against deleting a Department Head with cases
        // still assigned to them — same open gap Phase 3 flagged for
        // Department deletion. Relies on whatever FK rule Phase 1 put on
        // case_records.assigned_to. Worth a proper check before defense.
        $departmentHead->delete();
    }
}
