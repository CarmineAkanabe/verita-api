<?php

namespace App\Services;

use App\DTO\UpdateProfileData;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AccountService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function updateProfile(User $user, UpdateProfileData $data): User
    {
        DB::transaction(function () use ($user, $data) {
            $data->firstName !== null && $user->first_name = $data->firstName;
            $data->lastName !== null && $user->last_name = $data->lastName;
            $data->email !== null && $user->email = $data->email;
            $data->password !== null && $user->password = $data->password;
            if ($data->profilePicture !== null) {
                $user->profile_picture = $data->profilePicture->store('avatars', 'public');
            }
            $user->save();
        });

        return $user->fresh();
    }

    public function dashboard(User $user): array
    {
        return match ($user->role) {
            Role::DEPARTMENT_HEAD => [
                'role' => Role::DEPARTMENT_HEAD->value,
                'department' => $user->department?->name,
                'assignedCaseCount' => $user->assignedCases()->count(),
            ],
            Role::MANAGER => [
                'role' => Role::MANAGER->value,
                'departmentCount' => Department::count(),
                'userCount' => User::count(),
            ],
        };
    }
}
