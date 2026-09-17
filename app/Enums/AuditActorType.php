<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditActorType: string
{
    case AI = 'AI';
    case DEPARTMENT_HEAD = 'DEPARTMENT_HEAD';
    case SYSTEM = 'SYSTEM';
}
