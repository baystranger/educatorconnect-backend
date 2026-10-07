<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts are saved before every field is filled in; publishing enforces completeness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_jobs', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
            $table->string('category')->nullable()->change();
            $table->string('employment_type')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('province')->nullable()->change();
            $table->string('postal_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_jobs', function (Blueprint $table): void {
            $table->text('description')->nullable(false)->change();
            $table->string('category')->nullable(false)->change();
            $table->string('employment_type')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->string('province')->nullable(false)->change();
            $table->string('postal_code')->nullable(false)->change();
        });
    }
};
