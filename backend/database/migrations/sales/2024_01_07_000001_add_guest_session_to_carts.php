<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-017 Guest Cart desteği. Cart artık ya user_id'ye ya da
 * guest_session_id'ye ait. XOR garantisi DB-side chk_carts_owner
 * constraint'i ile.
 *
 * Mevcut user cart'lar etkilenmez — yalnızca user_id NULL'a açıldı,
 * mevcut row'lar dolu kaldı. Eski uniq_carts_user_active partial
 * index güncellenip yerine 2 ayrı user/guest unique index geldi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales.carts', function (Blueprint $table) {
            // Guest cart için user_id null olabilir
            $table->uuid('user_id')->nullable()->change();

            // Guest session ID (UUID v4 string)
            $table->string('guest_session_id', 36)->nullable()->after('user_id');

            // 30 gün retention — cleanup job için
            $table->timestampTz('expires_at')->nullable()->after('status');
        });

        // Eski user-only unique partial index'i bırak; ikiye böl
        DB::statement('DROP INDEX IF EXISTS uniq_carts_user_active');

        // User cart için: tek aktif/user
        DB::statement(
            "CREATE UNIQUE INDEX uniq_carts_user_active "
            . "ON sales.carts (user_id) "
            . "WHERE status = 'active' AND user_id IS NOT NULL"
        );

        // Guest cart için: tek aktif/session
        DB::statement(
            "CREATE UNIQUE INDEX uniq_carts_guest_active "
            . "ON sales.carts (guest_session_id) "
            . "WHERE status = 'active' AND guest_session_id IS NOT NULL"
        );

        // Owner XOR — domain invariant DB-side garantisi
        DB::statement(
            "ALTER TABLE sales.carts ADD CONSTRAINT chk_carts_owner CHECK ("
            . "(user_id IS NOT NULL AND guest_session_id IS NULL) "
            . "OR (user_id IS NULL AND guest_session_id IS NOT NULL))"
        );

        // Cleanup job indeksi — yalnızca aktif guest cart'ları tarar
        DB::statement(
            "CREATE INDEX idx_carts_expires_at "
            . "ON sales.carts (expires_at) "
            . "WHERE status = 'active' AND guest_session_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales.carts DROP CONSTRAINT IF EXISTS chk_carts_owner');
        DB::statement('DROP INDEX IF EXISTS uniq_carts_guest_active');
        DB::statement('DROP INDEX IF EXISTS uniq_carts_user_active');
        DB::statement('DROP INDEX IF EXISTS idx_carts_expires_at');

        Schema::table('sales.carts', function (Blueprint $table) {
            $table->dropColumn(['guest_session_id', 'expires_at']);
        });

        // user_id NOT NULL'a geri dönüyor — guest row'lar varsa down() patlar
        // (kasıtlı; veri kaybı önlemi)
        Schema::table('sales.carts', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
        });

        DB::statement(
            "CREATE UNIQUE INDEX uniq_carts_user_active "
            . "ON sales.carts (user_id) WHERE status = 'active'"
        );
    }
};
