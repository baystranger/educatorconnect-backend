<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('educator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('slug')->unique();
            $table->string('professional_title')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('professional_summary')->nullable();
            $table->text('teaching_philosophy')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->json('languages')->nullable();
            $table->json('certifications')->nullable();
            $table->json('education')->nullable();
            $table->json('age_groups_preferred')->nullable();
            $table->json('areas_of_expertise')->nullable();
            $table->json('special_interests')->nullable();
            $table->text('professional_development')->nullable();
            $table->boolean('first_aid_cpr')->default(false);
            $table->string('criminal_record_check_status')->nullable();
            $table->string('resume_path')->nullable();
            $table->json('portfolio')->nullable();
            $table->string('introduction_video_path')->nullable();
            $table->json('availability')->nullable();
            $table->json('employment_preferences')->nullable();
            $table->boolean('open_to_relocation')->default(false);
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable()->index();
            $table->string('postal_code')->nullable()->index();
            $table->string('country')->default('Canada');
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void { Schema::dropIfExists('educator_profiles'); }
};
