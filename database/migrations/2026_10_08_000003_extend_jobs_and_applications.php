<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_jobs', function (Blueprint $table): void {
            $table->boolean('show_pay')->default(true)->after('salary_period');
            $table->string('experience_level')->nullable()->after('required_experience');
            $table->timestamp('archived_at')->nullable()->after('closed_at');
            $table->index(['childcare_centre_id', 'status']);
            $table->index(['status', 'published_at']);
        });

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->timestamp('interview_at')->nullable()->after('status');
            $table->string('interview_location')->nullable()->after('interview_at');
            $table->text('interview_details')->nullable()->after('interview_location');
            $table->timestamp('centre_seen_at')->nullable()->after('reviewed_at');
            $table->timestamp('status_changed_at')->nullable()->after('centre_seen_at');
            $table->index(['educator_id', 'status']);
            $table->index(['job_id', 'status']);
        });

        Schema::create('application_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('note')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['job_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_events');

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropIndex(['educator_id', 'status']);
            $table->dropIndex(['job_id', 'status']);
            $table->dropColumn(['interview_at', 'interview_location', 'interview_details', 'centre_seen_at', 'status_changed_at']);
        });

        Schema::table('user_jobs', function (Blueprint $table): void {
            $table->dropIndex(['childcare_centre_id', 'status']);
            $table->dropIndex(['status', 'published_at']);
            $table->dropColumn(['show_pay', 'experience_level', 'archived_at']);
        });
    }
};
