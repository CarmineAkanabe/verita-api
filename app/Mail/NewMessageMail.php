<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
// use Illuminate\Mail\Mailables\Attachment;
// use Illuminate\Mail\Mailables\Content;
// use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Message $message) {}
    public function build(): self
    {
        return $this->subject("New message on case #{$this->message->case_record_id}")
            ->markdown('mail.new-message');
    }
}
