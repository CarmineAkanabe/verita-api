<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_record_id' => CaseRecord::factory(),
            'actor_type' => AuditActorType::SYSTEM,
            'action' => AuditAction::STATUS_CHANGED,
            'previous_value' => 'SUBMITTED',
            'new_value' => 'AI_PROCESSING',
            'logged_at' => now(),
        ];
    }
}
