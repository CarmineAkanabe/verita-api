<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvidenceFileType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['case_record_id', 'file_path', 'file_type', 'uploaded_at'])]
class Evidence extends Model
{
    /** @use HasFactory<\Database\Factories\EvidenceFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'file_type' => EvidenceFileType::class,
            'uploaded_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }
}
