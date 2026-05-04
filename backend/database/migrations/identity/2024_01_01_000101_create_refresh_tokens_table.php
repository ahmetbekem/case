<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('identity.refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 64);
            $table->uuid('family_id');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('replaced_by_token_id')->nullable();
            $table->string('revoke_reason', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('user_id')
                ->references('id')->on('identity.users')
                ->onDelete('restrict');

            // NOTE: self-referencing FK (replaced_by_token_id) is added below
            // via ALTER TABLE — Laravel's inline foreign() emits the constraint
            // before the PRIMARY KEY is materialised, which makes Postgres reject
            // the reference target as "not unique".

            $table->unique('token_hash', 'uniq_refresh_tokens_token_hash');
            $table->index('user_id', 'idx_refresh_tokens_user_id');
            $table->index('family_id', 'idx_refresh_tokens_family_id');
            $table->index('expires_at', 'idx_refresh_tokens_expires_at');
        });

        DB::statement('ALTER TABLE identity.refresh_tokens ADD CONSTRAINT fk_refresh_tokens_replaced_by_token_id FOREIGN KEY (replaced_by_token_id) REFERENCES identity.refresh_tokens(id) ON DELETE SET NULL');

        DB::statement("ALTER TABLE identity.refresh_tokens ADD CONSTRAINT chk_refresh_tokens_revoke_reason CHECK (revoke_reason IS NULL OR revoke_reason IN ('logout', 'rotated', 'replay_detected', 'admin_revoke', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.refresh_tokens');
    }
};
