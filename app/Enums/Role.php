<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case DEPARTMENT_HEAD = 'DEPARTMENT_HEAD';
    case MANAGER = 'MANAGER';
}
