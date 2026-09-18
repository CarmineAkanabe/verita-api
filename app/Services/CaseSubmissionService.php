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

        $case = DB::transaction(function () use ($data, $plainPin) {
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

            // foreach ($data->evidenceFiles as $file) {
            //     $path = $file->store("evidence/{$case->id}"); // private 'local' disk

            //     Evidence::create([
            //         'case_record_id' => $case->id,
            //         'file_path' => $path,
            //         'file_type' => str_contains($file->getMimeType(), 'pdf')
            //             ? EvidenceFileType::PDF
            //             : EvidenceFileType::IMAGE,
            //         'uploaded_at' => now(),
            //     ]);
            // }

            $this->evidence->store($case, $data->evidenceFiles);

            $this->aiProcessing->dispatch($case);

            return ['case' => $case, 'trackingPin' => $plainPin];
        });

        // Dispatched after the transaction commits — never inside it, same
        // rule Phase 9 states explicitly for the chat broadcast.
        ProcessCaseWithAiJob::dispatch($case);

        return ['case' => $case, 'trackingPin' => $plainPin];
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
