<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professional_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('professional_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('professional_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('photo_or_logo_path')->nullable();
            $table->string('professional_title')->nullable();
            $table->text('description')->nullable();
            $table->json('services_offered')->nullable();
            $table->json('credentials')->nullable();
            $table->string('address')->nullable();
            $table->string('service_area')->nullable();
            $table->string('city')->index();
            $table->string('province')->index();
            $table->string('postal_code')->index();
            $table->string('country')->default('Canada');
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 10, 7)->nullable()->index();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('book_consultation')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('unclaimed')->index();
            $table->unsignedBigInteger('profile_views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('professional_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('business_domain_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('credential_information')->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_claims');
        Schema::dropIfExists('professional_listings');
        Schema::dropIfExists('professional_categories');
    }
};
