<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caseRecordId' => $this->case_record_id,
            'actorType' => $this->actor_type instanceof \BackedEnum ? $this->actor_type->value : (string) $this->actor_type,
            'action' => $this->action instanceof \BackedEnum ? $this->action->value : (string) $this->action,
            'previousValue' => $this->previous_value,
            'newValue' => $this->new_value,
            'note' => $this->note,
            'loggedAt' => $this->logged_at instanceof \DateTimeInterface 
                ? $this->logged_at->toIso8601String() 
                : (string) $this->logged_at,
        ];
    }
}