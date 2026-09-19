<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Events\CaseEscalated;
use App\Mail\CaseOutcomeMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendCaseEscalatedNotification implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(CaseEscalated $event): void
    {
        foreach (User::where('role', Role::MANAGER)->get() as $manager) {
            $this->notifications->record(
                $manager,
                NotificationType::CASE_ESCALATED,
                'Case escalated',
                "Case {$event->case->id} was escalated.",
                true
            );
            Mail::to($manager)->queue(new CaseOutcomeMail($event->case, 'escalated'));
        }
    }
}
