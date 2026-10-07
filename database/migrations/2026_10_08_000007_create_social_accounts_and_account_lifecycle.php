<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A user can link several sign-in providers; the single oauth_* columns on users are legacy.
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        DB::table('users')
            ->whereNotNull('oauth_provider')
            ->whereNotNull('oauth_provider_id')
            ->orderBy('id')
            ->each(function ($user): void {
                DB::table('social_accounts')->insertOrIgnore([
                    'user_id' => $user->id,
                    'provider' => $user->oauth_provider,
                    'provider_user_id' => $user->oauth_provider_id,
                    'email' => $user->email,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deactivated_at')->nullable()->after('last_login_at');
        });

        Schema::table('user_jobs', function (Blueprint $table): void {
            $table->string('practicum_level', 30)->nullable()->after('experience_level');
            $table->unsignedSmallInteger('practicum_hours')->nullable()->after('practicum_level');
            $table->string('partner_institutions')->nullable()->after('practicum_hours');
        });
    }

    public function down(): void
    {
        Schema::table('user_jobs', fn (Blueprint $table) => $table->dropColumn(['practicum_level', 'practicum_hours', 'partner_institutions']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('deactivated_at'));
        Schema::dropIfExists('social_accounts');
    }
};
