<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the educator chose to share with the centre when applying (intro video, certifications).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->json('shared_items')->nullable()->after('resume_path');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', fn (Blueprint $table) => $table->dropColumn('shared_items'));
    }
};
