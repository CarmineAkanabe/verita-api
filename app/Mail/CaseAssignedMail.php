<?php

namespace App\Mail;

use App\Models\CaseRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CaseAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CaseRecord $case) {}

    public function build(): self
    {
        $shortId = strtoupper(substr($this->case->id, 0, 8));
        $category = $this->case->category?->value ?? $this->case->category ?? 'Investigation';

        return $this->subject("[Verita] Case Assigned: #{$shortId} ({$category}) — Action Required")
            ->markdown('email.case-assigned')
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/verita-logo.png'), 'verita-logo.png', 'image/png');
                $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', $this->case->id);
                $message->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });
    }
}