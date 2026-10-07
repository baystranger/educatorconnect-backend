<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('childcare_centre_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('parent_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('child_age')->nullable();
            $table->string('program_of_interest')->nullable();
            $table->string('preferred_contact_method')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('new')->index();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('parent_requests'); }
};
