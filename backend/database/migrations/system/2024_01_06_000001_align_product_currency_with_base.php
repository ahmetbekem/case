<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-016 Single Base Currency hizalama migration'ı.
 *
 * Tüm catalog.products.currency değerlerini system.settings.base_currency
 * ile aynı hale getirir. Migration sırası: system seeder products migration'ı
 * önce gelir; settings tablosu boşsa fallback 'TRY'.
 *
 * NOT: cart_items'lar bu noktada silinir — eski snapshot fiyat farklı
 * currency'deydi (örn USD ürün TRY'ye değişti); price snapshot mismatch
 * olur. Sepet boşaltılır, kullanıcı yeniden ekler. **Order'lara DOKUNULMAZ
 * — historical snapshot integrity korunur** (eski siparişler eski
 * currency'de raporlanmaya devam eder).
 *
 * down(): mevcut currency'leri geri yüklemek imkansız (orijinal kayıp).
 * Yalnızca DEFAULT constraint'i kaldırır.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) Base currency'yi system.settings'ten oku; yoksa 'TRY' fallback.
        $baseCurrency = DB::table('system.settings')
            ->where('key', 'base_currency')
            ->value('value') ?? 'TRY';

        // 2) Cart_items + carts boşalt (snapshot mismatch riskine karşı).
        //    cart_items.cart_id ON DELETE CASCADE — carts silince items da gider.
        DB::statement('TRUNCATE TABLE sales.cart_items, sales.carts RESTART IDENTITY CASCADE');

        // 3) Tüm ürünlerin currency'sini base'e align et.
        DB::table('catalog.products')
            ->where('currency', '!=', $baseCurrency)
            ->update(['currency' => $baseCurrency, 'updated_at' => now()]);

        // 4) DEFAULT constraint set — sonraki INSERT'lerde currency
        //    geçilmezse otomatik base'e düşsün.
        Schema::table('catalog.products', function (Blueprint $table) use ($baseCurrency) {
            $table->string('currency', 3)->default($baseCurrency)->change();
        });
    }

    public function down(): void
    {
        Schema::table('catalog.products', function (Blueprint $table) {
            $table->string('currency', 3)->default(null)->change();
        });
    }
};
