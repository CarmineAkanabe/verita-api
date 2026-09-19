<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\PresenceStatus;
use App\Enums\SenderType;
use App\Events\MessageSent;
use App\Mail\NewMessageMail;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendNewMessageNotification implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        if ($message->sender_type !== SenderType::CASE_REPORTER) return;

        $head = $message->caseRecord->assignedTo;
        if (!$head || $head->presence_status !== PresenceStatus::OFFLINE) return;

        $this->notifications->record(
            $head,
            NotificationType::NEW_MESSAGE,
            'New message',
            "New message on case {$message->case_record_id}.",
            true
        );
        Mail::to($head)->queue(new NewMessageMail($message));
    }
}
