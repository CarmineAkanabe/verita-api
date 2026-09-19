<?php

use App\Enums\Role;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('case.{caseId}', function ($user, string $caseId) {
    if ($user instanceof CaseRecord) {
        return $user->id === $caseId;
    }

    if ($user instanceof User) {
        $case = CaseRecord::find($caseId);
        return $case !== null
            && $user->role === Role::DEPARTMENT_HEAD
            && $case->assigned_to === $user->id;
    }

    return false;
}, ['guards' => ['case-api', 'api']]);
