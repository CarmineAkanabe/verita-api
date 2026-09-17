<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Department;
use App\Models\Evidence;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $departments = collect(['Software Engineering', 'Graphics Design', 'Networking'])
            ->map(fn($name) => Department::factory()->create(['name' => $name]));

        $departments->each(function ($dept) {
            User::factory()->count(2)->create([
                'role' => Role::DEPARTMENT_HEAD,
                'department_id' => $dept->id,
            ]);
        });

        User::factory()->manager()->create([
            'email' => 'manager@digimark.test',
        ]);

        $swDept = $departments->first();
        $head = User::where('department_id', $swDept->id)->first();

        $case = CaseRecord::factory()->create([
            'department_id' => $swDept->id,
            'status' => CaseStatus::AWAITING_REVIEW,
            'ai_summary' => 'Reporter alleges misuse of project funds for personal expenses.',
            'ai_timeline' => ['2025-08-01: Funds requested', '2025-08-15: Discrepancy noticed'],
        ]);

        Evidence::factory()->count(2)->create(['case_record_id' => $case->id]);

        Message::factory()->count(3)->create(['case_record_id' => $case->id]);

        AuditLog::factory()->create([
            'case_record_id' => $case->id,
            'action' => AuditAction::AI_PROCESSED,
            'previous_value' => 'AI_PROCESSING',
            'new_value' => 'AWAITING_REVIEW',
        ]);
    }
}
