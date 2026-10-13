<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_listings', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->index(['owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('professional_listings', function (Blueprint $table) {
            $table->dropIndex(['owner_user_id', 'status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_notes', 'reviewed_at']);
        });
    }
};
