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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');
            $table->string('workflow_type');
            $table->foreignId('requested_by')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('subject_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_step_order')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['workflow_type', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
