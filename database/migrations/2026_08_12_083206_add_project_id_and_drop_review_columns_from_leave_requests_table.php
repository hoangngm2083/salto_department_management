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
        Schema::table('leave_requests', function (Blueprint $table) {
            // MySQL uses `leave_requests_employee_id_status_index` (employee_id, status) as the
            // implicit FK-supporting index for employee_id - it must be replaced with a plain
            // single-column index *before* being dropped, or the FK is left without any index.
            $table->index('employee_id');
            $table->dropIndex(['employee_id', 'status']);
            $table->dropIndex(['status', 'start_date']);

            $table->foreignId('project_id')->nullable()->after('employee_id')->constrained()->cascadeOnDelete();

            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'review_note', 'reminder_sent_at']);

            $table->index('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
            $table->dropConstrainedForeignId('project_id');

            $table->string('status')->default('pending')->after('reason');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'start_date']);
            $table->dropIndex(['employee_id']);
        });
    }
};
