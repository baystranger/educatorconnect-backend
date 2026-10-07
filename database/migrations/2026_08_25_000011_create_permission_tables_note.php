<?php
// Spatie publishes its own permission migration. Run:
// php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
// This file intentionally contains no schema migration.
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {}
    public function down(): void {}
};
