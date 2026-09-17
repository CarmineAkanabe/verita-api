<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatus: string
{
    case UNREAD = 'UNREAD';
    case READ = 'READ';
}
