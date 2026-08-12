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
        Schema::create('job_posting_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->string('external_ref')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['job_posting_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_posting_channels');
    }
};
