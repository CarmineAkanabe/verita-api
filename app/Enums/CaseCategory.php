<?php

declare(strict_types=1);

namespace App\Enums;

enum CaseCategory: string
{
    case FRAUD = 'FRAUD';
    case HARASSMENT = 'HARASSMENT';
    case SECURITY = 'SECURITY';
    case OTHER = 'OTHER';
}
