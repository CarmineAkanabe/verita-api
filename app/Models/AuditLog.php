<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['case_id', 'actor_type', 'action', 'previous_value', 'new_value', 'logged_at'])]
class AuditLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'action' => AuditAction::class,
            'logged_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }
}
