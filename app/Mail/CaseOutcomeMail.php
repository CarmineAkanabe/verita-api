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

class CaseOutcomeMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public CaseRecord $case, public string $reason) {}
    public function build(): self
    {
        return $this->subject("Case #{$this->case->id} {$this->reason}")
            ->markdown('mail.case-outcome');
    }
}
