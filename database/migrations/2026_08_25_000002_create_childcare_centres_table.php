<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('childcare_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('about')->nullable();
            $table->text('philosophy')->nullable();
            $table->text('mission_values')->nullable();
            $table->text('daily_routine')->nullable();
            $table->text('teaching_approach')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('welcome_video_path')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->index();
            $table->string('province')->index();
            $table->string('postal_code')->index();
            $table->string('country')->default('Canada');
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->json('social_links')->nullable();
            $table->json('hours_of_operation')->nullable();
            $table->string('licensing_status')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('profile_views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void { Schema::dropIfExists('childcare_centres'); }
};
