<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Events\CaseReadyForReview;
use App\Mail\CaseReadyForReviewMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendCaseReadyForReviewNotifications implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(CaseReadyForReview $event): void
    {
        $heads = User::where('role', Role::DEPARTMENT_HEAD)
            ->where('department_id', $event->case->department_id)->get();

        foreach ($heads as $head) {
            $this->notifications->record(
                $head,
                NotificationType::CASE_READY_FOR_REVIEW,
                'Case ready for review',
                "Case {$event->case->id} is ready for review.",
                true
            );
            Mail::to($head)->queue(new CaseReadyForReviewMail($event->case));
        }
    }
}
