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
        Schema::create('role_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('change_mode');
            $table->foreignId('from_project_role_id')->nullable()->constrained('project_roles')->nullOnDelete();
            $table->foreignId('to_project_role_id')->nullable()->constrained('project_roles')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_change_requests');
    }
};
