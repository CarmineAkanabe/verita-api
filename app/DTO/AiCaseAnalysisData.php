<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class AiCaseAnalysisData extends Data
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $summary,
        public array $timeline,
        public array $completeness,
        public array $consistency,
        public array $clarifications,
    ) {}
}
