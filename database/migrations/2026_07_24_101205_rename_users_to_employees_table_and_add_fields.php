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
        Schema::rename('users', 'employees');

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->after('id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->date('birthday')->after('name');
            $table->enum('position', ['employee', 'manager', 'admin'])->default('employee')->after('birthday');
            $table->softDeletes()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'position', 'birthday']);
            $table->dropSoftDeletes();
        });

        Schema::rename('employees', 'users');
    }
};
