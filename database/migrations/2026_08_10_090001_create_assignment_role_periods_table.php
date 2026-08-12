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
        Schema::create('assignment_role_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_assignment_id')->constrained('project_assignments')->cascadeOnDelete();
            $table->foreignId('project_role_id')->constrained('project_roles')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            // No FK constraint yet: approval_requests doesn't exist until Phase D,
            // which will add it via its own migration once the table exists.
            $table->unsignedBigInteger('source_approval_request_id')->nullable();
            $table->timestamps();
            $table->index(['project_assignment_id', 'project_role_id'], 'assignment_role_periods_assignment_role_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_role_periods');
    }
};
