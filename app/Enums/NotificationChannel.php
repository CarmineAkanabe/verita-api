<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannel: string
{
    case IN_APP = 'IN_APP';
    case IN_APP_AND_EMAIL = 'IN_APP_AND_EMAIL';
}
