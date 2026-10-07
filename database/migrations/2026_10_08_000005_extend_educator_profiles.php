<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educator_profiles', function (Blueprint $table): void {
            $table->boolean('open_to_work')->default(true)->after('is_published');
            $table->string('visibility', 30)->default('verified_centres')->after('open_to_work');
            $table->date('available_from')->nullable()->after('availability');
            $table->unsignedSmallInteger('search_radius_km')->default(25)->after('available_from');
            $table->date('criminal_record_checked_on')->nullable()->after('criminal_record_check_status');
            $table->boolean('show_criminal_record_status')->default(true)->after('criminal_record_checked_on');
            $table->string('resume_original_name')->nullable()->after('resume_path');
            $table->unsignedInteger('resume_size')->nullable()->after('resume_original_name');
            $table->timestamp('setup_dismissed_at')->nullable();
        });

        Schema::create('educator_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('educator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('title');
            $table->string('issuer')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['educator_profile_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educator_credentials');
        Schema::table('educator_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'open_to_work', 'visibility', 'available_from', 'search_radius_km',
                'criminal_record_checked_on', 'show_criminal_record_status',
                'resume_original_name', 'resume_size', 'setup_dismissed_at',
            ]);
        });
    }
};
