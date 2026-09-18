<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceResource extends JsonResource
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
            'fileType' => $this->file_type->value,
            'uploadedAt' => $this->uploaded_at,
            'downloadUrl' => route('cases.me.evidence.show', ['evidence' => $this->id]),
        ];
    }
}
