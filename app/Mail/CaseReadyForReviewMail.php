<?php

namespace App\Mail;

use App\Models\CaseRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CaseReadyForReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CaseRecord $case) {}

    public function build(): self
    {
        $shortId = strtoupper(substr($this->case->id, 0, 8));
        $dept = $this->case->department?->name ?? 'Department';

        return $this->subject("[Verita] New Intake in {$dept}: #{$shortId} Ready for Review")
            ->markdown('email.case-ready-for-review')
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/verita-logo.png'), 'verita-logo.png', 'image/png');
                $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', $this->case->id);
                $message->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });
    }
}