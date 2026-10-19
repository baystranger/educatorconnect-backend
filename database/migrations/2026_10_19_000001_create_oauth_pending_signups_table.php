<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Google/LinkedIn sign-up waits here, keyed by a single-use token, while the
     * person adds the details the provider doesn't share (location, centre info).
     */
    public function up(): void
    {
        Schema::create('oauth_pending_signups', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('provider', 30);
            $table->string('provider_user_id');
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('role', 30);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_pending_signups');
    }
};
