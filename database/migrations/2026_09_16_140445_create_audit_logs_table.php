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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_record_id')->constrained('case_records')->cascadeOnDelete();
            $table->string('actor_type');
            $table->string('action');
            $table->string('previous_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('note')->nullable()->after('new_value');
            $table->timestamp('logged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
