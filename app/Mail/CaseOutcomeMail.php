<?php

namespace App\Mail;

use App\Models\CaseRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CaseOutcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CaseRecord $case, public string $reason) {}

    public function build(): self
    {
        $shortId = strtoupper(substr($this->case->id, 0, 8));
        $action = ucfirst($this->reason);

        return $this->subject("[Verita] Incident Case #{$shortId} {$action} — Notification")
            ->markdown('email.case-outcome')
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/verita-logo.png'), 'verita-logo.png', 'image/png');
                $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', $this->case->id);
                $message->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });
    }
}