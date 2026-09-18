<?php

namespace App\DTO;

use App\Enums\CaseStatus;

readonly class UpdateCaseStatusData
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public CaseStatus $status,
        public string $note,
        public ?string $resolutionSummary,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            status: CaseStatus::from($validated['status']),
            note: $validated['note'],
            resolutionSummary: $validated['resolutionSummary'] ?? null,
        );
    }
}
