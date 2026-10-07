<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * job_applications.job_id and saved_jobs.job_id were constrained to the queue's
 * `jobs` table instead of the job postings table (`user_jobs`). Re-point them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['job_applications', 'saved_jobs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['job_id']);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('job_id')->references('id')->on('user_jobs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['job_applications', 'saved_jobs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['job_id']);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('job_id')->references('id')->on('jobs')->cascadeOnDelete();
            });
        }
    }
};
