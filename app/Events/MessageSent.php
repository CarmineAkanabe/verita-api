<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('case.' . $this->message->case_record_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $dh = $this->message->caseRecord?->assignedTo;
        return [
            'id' => $this->message->id,
            'caseId' => $this->message->case_record_id,
            'senderType' => $this->message->sender_type->value,
            'content' => $this->message->content,
            'sentAt' => $this->message->sent_at,
            'presenceStatus' => $dh?->presence_status instanceof \BackedEnum ? $dh->presence_status->value : ($dh?->presence_status ?? 'ONLINE'),
        ];
    }
}
