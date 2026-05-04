<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('identity.users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 320);
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password_hash', 255);
            $table->string('full_name', 255);
            $table->string('role', 32)->default('customer');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::statement("ALTER TABLE identity.users ADD CONSTRAINT chk_users_role CHECK (role IN ('customer', 'admin'))");
        DB::statement('ALTER TABLE identity.users ADD CONSTRAINT chk_users_email_lowercase CHECK (email = LOWER(email))');

        // Partial unique index: email is only unique among non-soft-deleted rows
        DB::statement('CREATE UNIQUE INDEX uniq_users_email ON identity.users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_users_role ON identity.users (role) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_users_created_at ON identity.users (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.users');
    }
};
