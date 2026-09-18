<?php

namespace App\Services;

use App\DTO\CaseSubmissionData;
use App\Enums\CaseCategory;
use App\Enums\CaseStatus;
// use App\Enums\EvidenceFileType;
use App\Jobs\ProcessCaseWithAiJob;
use App\Models\CaseRecord;
// use App\Models\Evidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CaseSubmissionService
{
    /**
     * Create a new class instance.
     */
    public function __construct(public AiProcessingService $aiProcessing, public EvidenceService $evidence) {}

    public function submit(CaseSubmissionData $data): array
    {
        $plainPin = $this->generatePin();

        // Rename the output variable to $result so it doesn't overwrite the model
        $result = DB::transaction(function () use ($data, $plainPin) {
            $case = CaseRecord::create([
                'tracking_pin_hash' => Hash::make($plainPin),
                'department_id' => $data->departmentId,
                'category' => CaseCategory::FRAUD,
                'description' => $data->description,
                'purpose_of_transaction' => $data->purposeOfTransaction,
                'amount_involved' => $data->amountInvolved,
                'person_involved' => $data->personInvolved,
                'transaction_date' => $data->transactionDate,
                'concerns_department_head' => $data->concernsDepartmentHead,
                'status' => CaseStatus::SUBMITTED,
            ]);

            $this->evidence->store($case, $data->evidenceFiles);

            $case->update(['status' => CaseStatus::AI_PROCESSING]);

            // Return the array, but DO NOT dispatch the AI job inside the transaction
            return ['case' => $case, 'trackingPin' => $plainPin];
        });

        // Extract the pure model from the result array and dispatch it here
        ProcessCaseWithAiJob::dispatch($result['case']);

        return $result;
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
