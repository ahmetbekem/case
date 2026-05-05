<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sepet kalemlerine productName + productSku snapshot kolonu — UI sepet'te
 * "{productId.slice(0,8)}…" yerine gerçek adı göstersin, soft-delete sonrası
 * da bilgi kaybolmasın (OrderItem ile aynı pattern).
 *
 * Backfill: var olan satırlar için catalog.products'tan JOIN ile doldurulur,
 * ardından NOT NULL eklenir. Eğer ilgili product silinmişse "[silinmiş ürün]"
 * placeholder yazılır (silmeyi catalog FK restrict zaten engelliyor; bu
 * defansif).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales.cart_items', function (Blueprint $table) {
            $table->string('product_name_snapshot', 255)->nullable()->after('product_id');
            $table->string('product_sku_snapshot', 64)->nullable()->after('product_name_snapshot');
        });

        DB::statement(<<<'SQL'
            UPDATE sales.cart_items ci
               SET product_name_snapshot = COALESCE(p.name, '[silinmiş ürün]'),
                   product_sku_snapshot  = COALESCE(p.sku, 'UNKNOWN')
              FROM catalog.products p
             WHERE ci.product_id = p.id
        SQL);

        // Hâlâ NULL kalan satır varsa (FK'ya rağmen edge case) defansif doldur
        DB::statement(<<<'SQL'
            UPDATE sales.cart_items
               SET product_name_snapshot = '[silinmiş ürün]',
                   product_sku_snapshot  = 'UNKNOWN'
             WHERE product_name_snapshot IS NULL OR product_sku_snapshot IS NULL
        SQL);

        Schema::table('sales.cart_items', function (Blueprint $table) {
            $table->string('product_name_snapshot', 255)->nullable(false)->change();
            $table->string('product_sku_snapshot', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales.cart_items', function (Blueprint $table) {
            $table->dropColumn(['product_name_snapshot', 'product_sku_snapshot']);
        });
    }
};
