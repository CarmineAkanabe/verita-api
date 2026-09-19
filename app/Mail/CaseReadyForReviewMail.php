<?php

namespace App\Mail;

use App\Models\CaseRecord;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
// use Illuminate\Mail\Mailables\Attachment;
// use Illuminate\Mail\Mailables\Content;
// use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaseReadyForReviewMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public CaseRecord $case) {}
    public function build(): self
    {
        return $this->subject("Case #{$this->case->id} ready for review")
            ->markdown('mail.case-ready-for-review');
    }
}
