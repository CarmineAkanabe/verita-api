<?php

namespace App\Http\Resources\V1;

use App\Enums\CaseStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseReporterDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aiReady = ! in_array($this->status, [
            CaseStatus::SUBMITTED,
            CaseStatus::AI_PROCESSING,
        ], true) && ! $this->ai_processing_failed;

        return [
            'caseId' => $this->id,
            'status' => $this->status->value,
            'description' => $this->description,
            'purposeOfTransaction' => $this->purpose_of_transaction,
            'amountInvolved' => $this->amount_involved,
            'personInvolved' => $this->person_involved,
            'transactionDate' => $this->transaction_date,
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
            'aiProcessingFailed' => $this->ai_processing_failed,
            'aiSummary' => $this->when($aiReady, $this->ai_summary),
            'aiTimeline' => $this->when($aiReady, $this->ai_timeline),
            'aiFindings' => $this->when($aiReady, $this->ai_findings),
        ];
    }
}
