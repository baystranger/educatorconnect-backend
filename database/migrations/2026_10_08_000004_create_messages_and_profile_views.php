<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One thread table for applicant <-> centre and parent <-> centre conversations.
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->morphs('conversation');
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 30);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_type', 'conversation_id', 'created_at']);
        });

        Schema::create('profile_view_events', function (Blueprint $table): void {
            $table->id();
            $table->morphs('viewable');
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('viewed_on');
            $table->timestamp('created_at')->nullable();
            $table->index(['viewable_type', 'viewable_id', 'viewed_on']);
        });

        Schema::table('parent_requests', function (Blueprint $table): void {
            $table->string('desired_start')->nullable()->after('program_of_interest');
            $table->string('schedule')->nullable()->after('desired_start');
            $table->timestamp('responded_at')->nullable()->after('status');
            $table->index(['childcare_centre_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('parent_requests', function (Blueprint $table): void {
            $table->dropIndex(['childcare_centre_id', 'type', 'status']);
            $table->dropColumn(['desired_start', 'schedule', 'responded_at']);
        });
        Schema::dropIfExists('profile_view_events');
        Schema::dropIfExists('messages');
    }
};
