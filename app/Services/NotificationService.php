<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;

final class NotificationService
{
    public function record(User $recipient, NotificationType $type, string $title, string $message, bool $mirrorEmail): Notification
    {
        return Notification::create([
            'user_id' => $recipient->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'status' => NotificationStatus::UNREAD,
            'channel' => $mirrorEmail ? NotificationChannel::IN_APP_AND_EMAIL : NotificationChannel::IN_APP,
            'sent_at' => now(),
        ]);
    }
}
