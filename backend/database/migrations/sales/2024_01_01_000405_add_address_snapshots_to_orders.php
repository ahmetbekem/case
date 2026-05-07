<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-018 — Orders artık delivery + billing adres snapshot'ı taşır.
 *
 * - delivery_address / billing_address: JSONB snapshot. Adres silinse bile
 *   sipariş immutable kalır (KVKK/audit). NULL'a izin var çünkü migration
 *   eski siparişlere geriye dönük dolduramayız; PlaceOrderHandler ADIM 7'de
 *   yeni siparişler için zorunlu hale geliyor.
 * - delivery_address_id / billing_address_id: nullable FK to identity.addresses.
 *   Adres silinince ON DELETE SET NULL — JSONB snapshot her hâlükârda korur.
 * - JSONB GIN index'i şimdilik yok — listings city filter'ları
 *   için ileride eklenebilir; küçük catalog için gereksiz.
 * - Analytics: sıkça "İstanbul'a giden siparişler" gibi sorgular için
 *   delivery_address->>'city' üzerinde btree index.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales.orders', function (Blueprint $table) {
            $table->jsonb('delivery_address')->nullable()->after('display_total');
            $table->jsonb('billing_address')->nullable()->after('delivery_address');
            $table->uuid('delivery_address_id')->nullable()->after('billing_address');
            $table->uuid('billing_address_id')->nullable()->after('delivery_address_id');

            $table->foreign('delivery_address_id')
                ->references('id')->on('identity.addresses')
                ->onDelete('set null');

            $table->foreign('billing_address_id')
                ->references('id')->on('identity.addresses')
                ->onDelete('set null');
        });

        // Analytics: city bazlı sipariş raporu için
        DB::statement(
            "CREATE INDEX idx_orders_delivery_city "
            . "ON sales.orders ((delivery_address->>'city')) "
            . "WHERE delivery_address IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sales.idx_orders_delivery_city');
        Schema::table('sales.orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_address_id']);
            $table->dropForeign(['billing_address_id']);
            $table->dropColumn([
                'delivery_address',
                'billing_address',
                'delivery_address_id',
                'billing_address_id',
            ]);
        });
    }
};
