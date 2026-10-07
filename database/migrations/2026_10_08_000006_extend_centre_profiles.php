<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('childcare_centres', function (Blueprint $table): void {
            $table->string('licence_type')->nullable()->after('licensing_status');
            $table->text('licence_number')->nullable()->after('licence_type');
            $table->json('teaching_approaches')->nullable()->after('teaching_approach');
            $table->json('daily_schedule')->nullable()->after('daily_routine');
            $table->json('highlights')->nullable()->after('daily_schedule');
            $table->string('welcome_video_title')->nullable()->after('welcome_video_path');
            $table->boolean('welcome_video_captions')->default(true)->after('welcome_video_title');
            $table->string('virtual_tour_url')->nullable()->after('welcome_video_captions');
            $table->text('tuition_info')->nullable();
            $table->text('tour_info')->nullable();
            $table->text('waitlist_info')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
        });

        Schema::table('centre_programs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_age_months')->nullable()->after('age_groups');
            $table->unsignedSmallInteger('max_age_months')->nullable()->after('min_age_months');
            $table->string('schedule', 30)->nullable()->after('max_age_months');
            $table->unsignedSmallInteger('capacity')->nullable()->after('schedule');
            $table->unsignedSmallInteger('educator_count')->nullable()->after('capacity');
            $table->string('availability', 20)->default('spaces')->after('educator_count');
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::table('centre_gallery_items', function (Blueprint $table): void {
            $table->string('alt_text')->nullable()->after('caption');
            $table->string('category', 40)->default('classrooms')->after('alt_text');
            $table->boolean('is_cover')->default(false)->after('category');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->index(['childcare_centre_id', 'sort_order']);
        });

        Schema::table('centre_team_members', function (Blueprint $table): void {
            $table->unsignedSmallInteger('years_experience')->nullable()->after('title');
            $table->string('credentials')->nullable()->after('years_experience');
        });

        Schema::table('centre_faqs', function (Blueprint $table): void {
            $table->index(['childcare_centre_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('centre_faqs', fn (Blueprint $table) => $table->dropIndex(['childcare_centre_id', 'sort_order']));
        Schema::table('centre_team_members', fn (Blueprint $table) => $table->dropColumn(['years_experience', 'credentials']));
        Schema::table('centre_gallery_items', function (Blueprint $table): void {
            $table->dropIndex(['childcare_centre_id', 'sort_order']);
            $table->dropColumn(['alt_text', 'category', 'is_cover', 'mime_type', 'size']);
        });
        Schema::table('centre_programs', fn (Blueprint $table) => $table->dropColumn([
            'min_age_months', 'max_age_months', 'schedule', 'capacity', 'educator_count', 'availability', 'sort_order',
        ]));
        Schema::table('childcare_centres', fn (Blueprint $table) => $table->dropColumn([
            'licence_type', 'licence_number', 'teaching_approaches', 'daily_schedule', 'highlights',
            'welcome_video_title', 'welcome_video_captions', 'virtual_tour_url', 'tuition_info',
            'tour_info', 'waitlist_info', 'submitted_at', 'published_at',
        ]));
    }
};
