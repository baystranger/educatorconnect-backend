<?php

use App\Support\Website;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Educators get a personal website / portfolio link, like centres and professionals already
 * have. Existing website values everywhere are made absolute ("site.ca" → "https://site.ca").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educator_profiles', function (Blueprint $table): void {
            $table->string('website')->nullable()->after('professional_title');
        });

        foreach (['childcare_centres', 'professional_listings'] as $tableName) {
            DB::table($tableName)->whereNotNull('website')->orderBy('id')->each(function ($row) use ($tableName): void {
                $normalized = Website::normalize($row->website);
                if ($normalized !== $row->website) {
                    DB::table($tableName)->where('id', $row->id)->update(['website' => $normalized]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('educator_profiles', function (Blueprint $table): void {
            $table->dropColumn('website');
        });
    }
};
