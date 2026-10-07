<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('educator_profile_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);

            $table->string('college_university')->nullable();
            $table->string('program')->nullable();

            $table->date('expected_graduation_date')->nullable();

            $table->string('practicum_level')->nullable();

            $table->date('practicum_start_date')->nullable();
            $table->date('practicum_end_date')->nullable();

            $table->unsignedInteger('required_practicum_hours')->nullable();

            $table->string('preferred_location')->nullable();

            $table->boolean('looking_for_practicum')->default(false);

            $table->timestamps();
        });

        Schema::create('practicum_opportunities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('childcare_centre_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->json('partner_institutions')->nullable();
            $table->json('programs_accepted')->nullable();

            $table->unsignedInteger('available_placements')
                ->default(1);

            $table->date('intake_start_date')->nullable();
            $table->date('intake_end_date')->nullable();

            $table->string('status')
                ->default('published')
                ->index();

            $table->timestamps();
        });

        Schema::create('practicum_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('practicum_opportunity_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('educator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status')
                ->default('new')
                ->index();

            $table->text('message')->nullable();

            $table->timestamp('applied_at');

            $table->timestamps();

            // Custom short index name to stay within MySQL's 64-character limit.
            $table->unique(
                ['practicum_opportunity_id', 'educator_id'],
                'practicum_app_opp_educator_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practicum_applications');
        Schema::dropIfExists('practicum_opportunities');
        Schema::dropIfExists('student_profiles');
    }
};