<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class NewMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Message $message) {}

    public function build(): self
    {
        $shortId = strtoupper(substr($this->message->case_record_id, 0, 8));

        return $this->subject("[Verita] New Whistleblower Message on Case #{$shortId}")
            ->markdown('email.new-message')
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/verita-logo.png'), 'verita-logo.png', 'image/png');
                $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', $this->message->case_record_id);
                $message->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });
    }
}