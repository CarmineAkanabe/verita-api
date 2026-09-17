<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseCategory;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tracking_pin_hash',
    'department_id',
    'category',
    'description',
    'purpose_of_transaction',
    'amount_involved',
    'person_involved',
    'transaction_date',
    'status',
    'assigned_to',
    'concerns_department_head',
])]
class CaseRecord extends Model
{
    /** @use HasFactory<\Database\Factories\CaseRecordFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'category' => CaseCategory::class,
            'status' => CaseStatus::class,
            'ai_timeline' => 'array',
            'ai_findings' => 'array',
            'transaction_date' => 'date',
            'amount_involved' => 'decimal:2',
            'resolved_at' => 'datetime',
            'concerns_department_head' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedDepartmentHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'case_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'case_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'case_id');
    }
}
