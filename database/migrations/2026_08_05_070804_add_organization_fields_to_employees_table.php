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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('current_level_id')->nullable()->after('department_id')->constrained('levels')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->after('current_level_id')->constrained('employees')->nullOnDelete();
            $table->string('status')->default('active')->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['current_level_id']);
            $table->dropForeign(['manager_employee_id']);
            $table->dropColumn(['current_level_id', 'manager_employee_id', 'status']);
        });
    }
};
