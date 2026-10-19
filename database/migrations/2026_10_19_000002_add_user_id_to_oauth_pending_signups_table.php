<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account a pending sign-up created, so a retried "Create account" (response lost
     * on a flaky network) signs the person in rather than failing as "already used".
     */
    public function up(): void
    {
        Schema::table('oauth_pending_signups', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('role')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_pending_signups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
