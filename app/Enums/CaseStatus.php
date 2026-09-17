<?php

declare(strict_types=1);

namespace App\Enums;

enum CaseStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case AI_PROCESSING = 'AI_PROCESSING';
    case AWAITING_REVIEW = 'AWAITING_REVIEW';
    case UNDER_INVESTIGATION = 'UNDER_INVESTIGATION';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
    case DISMISSED = 'DISMISSED';
}
