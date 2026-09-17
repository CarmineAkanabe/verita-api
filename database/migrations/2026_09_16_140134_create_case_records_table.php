<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_pin_hash');
            $table->foreignUuid('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('category');
            $table->text('description');
            $table->string('purpose_of_transaction');
            $table->decimal('amount_involved', 15, 2);
            $table->string('person_involved');
            $table->date('transaction_date');
            $table->text('ai_summary')->nullable();
            $table->json('ai_timeline')->nullable();
            $table->json('ai_findings')->nullable();
            $table->string('status')->default('SUBMITTED');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('concerns_department_head')->default(false);
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_records');
    }
};
