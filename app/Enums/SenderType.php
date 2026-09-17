<?php

declare(strict_types=1);

namespace App\Enums;

enum SenderType: string
{
    case CASE_REPORTER = 'CASE_REPORTER';
    case DEPARTMENT_HEAD = 'DEPARTMENT_HEAD';
}
