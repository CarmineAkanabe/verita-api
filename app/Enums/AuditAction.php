<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case STATUS_CHANGED = 'STATUS_CHANGED';
    case AI_PROCESSED = 'AI_PROCESSED';
    case EVIDENCE_REVIEWED = 'EVIDENCE_REVIEWED';
    case EVIDENCE_ADDED = 'EVIDENCE_ADDED';
    case MESSAGE_SENT = 'MESSAGE_SENT';
    case ESCALATED = 'ESCALATED';
}
