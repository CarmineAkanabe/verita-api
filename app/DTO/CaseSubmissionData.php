<?php

namespace App\DTO;

use App\Http\Requests\V1\StoreCaseRequest;

readonly class CaseSubmissionData
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public string $departmentId,
        public string $description,
        public string $purposeOfTransaction,
        public float $amountInvolved,
        public string $personInvolved,
        public string $transactionDate,
        public bool $concernsDepartmentHead,
        public array $evidenceFiles, // UploadedFile[]
    ) {}

    public static function fromRequest(StoreCaseRequest $request): self
    {
        return new self(
            departmentId: $request->validated('departmentId'),
            description: $request->validated('description'),
            purposeOfTransaction: $request->validated('purposeOfTransaction'),
            amountInvolved: (float) $request->validated('amountInvolved'),
            personInvolved: $request->validated('personInvolved'),
            transactionDate: $request->validated('transactionDate'),
            concernsDepartmentHead: (bool) $request->validated('concernsDepartmentHead'),
            evidenceFiles: $request->file('evidence', []),
        );
    }
}
