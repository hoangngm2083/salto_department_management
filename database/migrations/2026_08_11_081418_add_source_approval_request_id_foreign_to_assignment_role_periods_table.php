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
        Schema::table('assignment_role_periods', function (Blueprint $table) {
            $table->foreign('source_approval_request_id')->references('id')->on('approval_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_role_periods', function (Blueprint $table) {
            $table->dropForeign(['source_approval_request_id']);
        });
    }
};
