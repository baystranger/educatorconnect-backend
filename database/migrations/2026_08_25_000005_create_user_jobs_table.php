<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('category')->index();
            $table->string('employment_type')->index();
            $table->string('city')->index();
            $table->string('province')->index();
            $table->string('postal_code')->index();
            $table->string('country')->default('Canada');
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('salary_period')->nullable();
            $table->text('required_education')->nullable();
            $table->json('required_certifications')->nullable();
            $table->text('required_experience')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('benefits')->nullable();
            $table->date('application_deadline')->nullable()->index();
            $table->date('start_date')->nullable();
            $table->unsignedInteger('number_of_positions')->default(1);
            $table->text('application_instructions')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void { Schema::dropIfExists('user_jobs'); }
};
