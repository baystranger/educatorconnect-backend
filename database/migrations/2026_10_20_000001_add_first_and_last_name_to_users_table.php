<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The account holder's own first and last name, for every role. `name` stays the display
 * name (for a centre account that is the centre's name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
        });

        // Backfill: educators from their profile, centres from the contact person,
        // everyone else from the account name.
        DB::table('users')->orderBy('id')->each(function ($user): void {
            $source = match ($user->role) {
                'educator' => DB::table('educator_profiles')->where('user_id', $user->id)->first(['first_name', 'last_name']),
                'childcare_centre' => DB::table('childcare_centres')->where('owner_user_id', $user->id)->orderBy('id')->value('contact_person'),
                default => $user->name,
            };

            [$first, $last] = is_object($source)
                ? [$source->first_name, $source->last_name]
                : PersonName::split((string) $source);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first !== '' ? $first : null,
                'last_name' => $last !== '' ? $last : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
