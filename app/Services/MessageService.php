<?php

namespace App\Services;

use App\DTO\SendMessageData;
use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Enums\SenderType;
use App\Events\MessageSent;
use App\Models\CaseRecord;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function send(CaseRecord $case, SenderType $senderType, SendMessageData $data): Message
    {
        $message = DB::transaction(function () use ($case, $senderType, $data) {
            $message = Message::create([
                'case_record_id' => $case->id,
                'sender_type' => $senderType,
                'content' => $data->content,
                'sent_at' => now(),
            ]);

            if ($senderType === SenderType::DEPARTMENT_HEAD && $case->assignedTo) {
                if ($case->assignedTo->presence_status !== \App\Enums\PresenceStatus::ONLINE) {
                    $case->assignedTo->presence_status = \App\Enums\PresenceStatus::ONLINE;
                    $case->assignedTo->save();
                }
            }

            // Case Reporter has no AuditActorType value in the master spec's enum —
            // same gap Phase 7 hit with EVIDENCE_ADDED, same resolution: SYSTEM.
            $this->auditLog->log(
                case: $case,
                actorType: $senderType === SenderType::DEPARTMENT_HEAD
                    ? AuditActorType::DEPARTMENT_HEAD
                    : AuditActorType::SYSTEM,
                action: AuditAction::MESSAGE_SENT,
                previousValue: null,
                newValue: $senderType->value, // content itself deliberately not logged
            );

            return $message;
        });

        MessageSent::dispatch($message); // after commit, per Phase 6/8's rule

        return $message;
    }
}
