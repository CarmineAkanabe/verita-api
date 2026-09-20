<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseDetailResource extends JsonResource
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
            'category' => $this->category,
            'status' => $this->status,
            'description' => $this->description,
            'purposeOfTransaction' => $this->purpose_of_transaction,
            'amountInvolved' => $this->amount_involved,
            'personInvolved' => $this->person_involved,
            'transactionDate' => $this->transaction_date,
            'concernsDepartmentHead' => $this->concerns_department_head,
            'assignedTo' => $this->assigned_to,
            'resolutionSummary' => $this->resolution_summary,
            'createdAt' => $this->created_at,
            'resolvedAt' => $this->resolved_at,
            'escalatedAt' => $this->escalated_at,
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
            'aiSummary' => $this->ai_summary,
            'aiTimeline' => $this->ai_timeline,
            'aiFindings' => $this->ai_findings,
            'aiProcessingFailed' => $this->ai_processing_failed,
        ];
    }
}
