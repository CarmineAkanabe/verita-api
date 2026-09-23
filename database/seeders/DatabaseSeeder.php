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
            'email' => 'manager@verita.com',
        ]);

        $swDept = $departments->first();
        $swHeads = User::where('department_id', $swDept->id)->get();

        // AWAITING_REVIEW — main demo case, full AI review section
        $mainCase = CaseRecord::factory()->create([
            'department_id' => $swDept->id,
            'status' => CaseStatus::AWAITING_REVIEW,
            'ai_summary' => 'Reporter alleges misuse of project funds for personal expenses.',
            'ai_timeline' => ['2025-08-01: Funds requested', '2025-08-15: Discrepancy noticed'],
            'ai_findings' => [
                'completeness' => ['description' => true, 'amount' => true, 'person' => true, 'date' => true, 'evidence' => true],
                'consistency' => ['amount_matches_evidence' => true, 'date_consistent' => true],
                'clarification' => ['Evidence does not establish whether the promised item/service was delivered.'],
            ],
        ]);
        Evidence::factory()->count(2)->create(['case_record_id' => $mainCase->id]);
        Message::factory()->count(3)->create(['case_record_id' => $mainCase->id]);
        AuditLog::factory()->create([
            'case_record_id' => $mainCase->id,
            'action' => AuditAction::AI_PROCESSED,
            'previous_value' => 'AI_PROCESSING',
            'new_value' => 'AWAITING_REVIEW',
        ]);

        // Conflict-of-interest — appears in the Manager assignment queue.
        $assignmentCase = CaseRecord::factory()->create([
            'department_id' => $swDept->id,
            'status' => CaseStatus::AWAITING_REVIEW,
            'concerns_department_head' => true,
            'ai_summary' => 'Reporter indicates the report concerns a Department Head.',
            'ai_timeline' => ['2025-08-20: Report submitted for Manager assignment'],
        ]);
        Evidence::factory()->create(['case_record_id' => $assignmentCase->id]);

        // SUBMITTED — AI hasn't run yet, pending-state dashboard
        $submitted = CaseRecord::factory()->create(['department_id' => $swDept->id, 'status' => CaseStatus::SUBMITTED]);
        Evidence::factory()->create(['case_record_id' => $submitted->id]);

        // UNDER_INVESTIGATION — claimed, active chat
        $underInvestigation = CaseRecord::factory()->create([
            'department_id' => $swDept->id,
            'status' => CaseStatus::UNDER_INVESTIGATION,
            'assigned_to' => $swHeads->first()->id,
            'ai_summary' => 'Reporter alleges an unauthorized vendor payment.',
            'ai_timeline' => ['2025-07-10: Payment issued', '2025-07-20: Reporter flagged the transaction'],
        ]);
        Evidence::factory()->count(2)->create(['case_record_id' => $underInvestigation->id]);
        Message::factory()->count(5)->create(['case_record_id' => $underInvestigation->id]);
        AuditLog::factory()->create([
            'case_record_id' => $underInvestigation->id,
            'action' => AuditAction::STATUS_CHANGED,
            'previous_value' => 'AWAITING_REVIEW',
            'new_value' => 'UNDER_INVESTIGATION',
        ]);

        // RESOLVED — substantiated
        $resolved = CaseRecord::factory()->resolved()->create([
            'department_id' => $swDept->id,
            'assigned_to' => $swHeads->last()->id,
            'ai_summary' => 'Reporter alleges falsified receipts for a client dinner.',
            'ai_timeline' => ['2025-06-01: Receipt submitted', '2025-06-05: Amount flagged as inconsistent'],
        ]);
        Evidence::factory()->count(2)->create(['case_record_id' => $resolved->id]);
        AuditLog::factory()->create([
            'case_record_id' => $resolved->id,
            'action' => AuditAction::STATUS_CHANGED,
            'previous_value' => 'UNDER_INVESTIGATION',
            'new_value' => 'RESOLVED',
        ]);

        // DISMISSED — unsubstantiated
        $dismissed = CaseRecord::factory()->dismissed()->create([
            'department_id' => $swDept->id,
            'assigned_to' => $swHeads->first()->id,
            'ai_summary' => 'Reporter alleges a delayed reimbursement was fraudulent.',
            'ai_timeline' => ['2025-05-12: Reimbursement requested', '2025-05-30: Reimbursement processed'],
        ]);
        Evidence::factory()->create(['case_record_id' => $dismissed->id]);
        AuditLog::factory()->create([
            'case_record_id' => $dismissed->id,
            'action' => AuditAction::STATUS_CHANGED,
            'previous_value' => 'UNDER_INVESTIGATION',
            'new_value' => 'DISMISSED',
        ]);
    }
}
