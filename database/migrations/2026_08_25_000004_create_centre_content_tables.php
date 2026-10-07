<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('centre_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('age_groups')->nullable();
            $table->decimal('tuition_amount', 10, 2)->nullable();
            $table->string('tuition_frequency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('centre_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('image');
            $table->string('file_path');
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('centre_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('centre_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('centre_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centre_resources');
        Schema::dropIfExists('centre_faqs');
        Schema::dropIfExists('centre_team_members');
        Schema::dropIfExists('centre_gallery_items');
        Schema::dropIfExists('centre_programs');
    }
};
