<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CaseAssigned;
use App\Mail\CaseAssignedMail;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendCaseAssignedNotification implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(CaseAssigned $event): void
    {
        $head = $event->case->assignedTo; // adjust to your event's actual property
        $this->notifications->record(
            $head,
            NotificationType::CASE_ASSIGNED,
            'Case assigned to you',
            "Case {$event->case->id} has been assigned to you.",
            true
        );
        Mail::to($head)->queue(new CaseAssignedMail($event->case));
    }
}
