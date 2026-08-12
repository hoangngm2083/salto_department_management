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
        Schema::table('job_postings', function (Blueprint $table) {
            // The public careers listing (Phase R2) filters `status = published`
            // then sorts by `id desc` - the existing single-column `status` index
            // (Phase R1) doesn't cover the sort, so this composite lets both the
            // filter and the ORDER BY be satisfied from one index scan.
            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['status', 'id']);
        });
    }
};
