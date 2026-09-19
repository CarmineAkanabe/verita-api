<?php

namespace App\Enums;

enum NotificationType: string
{
    case CASE_READY_FOR_REVIEW = 'case_ready_for_review';
    case CASE_ASSIGNED = 'case_assigned';
    case NEW_MESSAGE = 'new_message';
    case CASE_RESOLVED = 'case_resolved';
    case CASE_ESCALATED = 'case_escalated';
}
