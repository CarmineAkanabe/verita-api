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
        if ($case === null) {
            return false;
        }

        if ($user->role === Role::MANAGER) {
            return true;
        }

        if ($user->role === Role::DEPARTMENT_HEAD && $case->assigned_to === $user->id) {
            if ($user->presence_status !== \App\Enums\PresenceStatus::ONLINE) {
                $user->presence_status = \App\Enums\PresenceStatus::ONLINE;
                $user->save();
            }
            return true;
        }
    }

    return false;
}, ['guards' => ['case-api', 'api']]);
