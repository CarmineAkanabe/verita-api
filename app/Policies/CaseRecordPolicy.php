<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Models\CaseRecord;
use App\Models\User;
// use Illuminate\Auth\Access\Response;

class CaseRecordPolicy
{
    public function claim(User $user, CaseRecord $case): bool
    {
        return $user->role === Role::DEPARTMENT_HEAD
            && $case->assigned_to === null
            && $case->department_id === $user->department_id
            && ! $case->concerns_department_head
            && $case->status === CaseStatus::AWAITING_REVIEW;
    }

    /** Assigned DH, or still-unclaimed queue-eligible DH (pre-claim read access). */
    public function view(User $user, CaseRecord $case): bool
    {
        if ($user->role !== Role::DEPARTMENT_HEAD) {
            return false;
        }

        if ($case->assigned_to === $user->id) {
            return true;
        }

        return $case->assigned_to === null
            && $case->department_id === $user->department_id
            && ! $case->concerns_department_head;
    }

    public function updateStatus(User $user, CaseRecord $case): bool
    {
        return $user->role === Role::DEPARTMENT_HEAD
            && $case->assigned_to === $user->id;
    }
}
