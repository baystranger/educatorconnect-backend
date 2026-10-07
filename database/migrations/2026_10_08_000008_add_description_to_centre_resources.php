<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centre_resources', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('centre_resources', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
