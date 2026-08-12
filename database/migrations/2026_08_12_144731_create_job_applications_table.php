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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('job_posting_id')->constrained('job_postings')->restrictOnDelete();
            $table->string('channel');
            $table->text('cover_letter')->nullable();
            $table->string('resume_path');
            $table->string('status')->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('talent_pool')->default(false);
            $table->string('offer_response')->nullable();
            $table->timestamp('offer_responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['applicant_id', 'job_posting_id']);
            $table->index(['job_posting_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
