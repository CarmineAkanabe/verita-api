<?php

namespace App\Services;

use App\Enums\EvidenceFileType;
use App\Models\CaseRecord;
use App\Models\Evidence;
use Illuminate\Support\Facades\DB;

class EvidenceService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function store(CaseRecord $case, array $files): void
    {
        DB::transaction(function () use ($case, $files) {
            foreach ($files as $file) {
                $path = $file->store("evidence/{$case->id}");

                Evidence::create([
                    'case_record_id' => $case->id,
                    'file_path' => $path,
                    'file_type' => str_contains($file->getMimeType(), 'pdf')
                        ? EvidenceFileType::PDF
                        : EvidenceFileType::IMAGE,
                    'uploaded_at' => now(),
                ]);
            }
        });
    }
}
