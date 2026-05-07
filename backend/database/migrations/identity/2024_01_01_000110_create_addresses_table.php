<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-018 Address Management.
 *
 * - identity.addresses: kullanıcı adres rehberi. Bir kullanıcının N (uygulama
 *   tarafında 10 ile sınırlı) adresi olabilir.
 * - Default delivery + default billing flag'leri için partial unique index'ler
 *   (DB-side garanti: bir kullanıcıda en fazla bir aktif default delivery/billing).
 * - Soft delete — order snapshot referansı korunsun, FK hala "var" görünür ama
 *   listings deleted_at IS NULL filter'ı ile hariç tutar.
 * - Phone CHECK regex: yalnızca rakam/+/space/parens/dash; uygulama tarafında
 *   PhoneNumber VO daha sıkı normalize eder (+E164 formatına).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('identity.addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');

            $table->string('title', 50);
            $table->string('full_name', 255);
            $table->string('phone', 20);

            $table->string('country_code', 2)->default('TR');
            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('neighborhood', 150)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('address_line');
            $table->text('address_line_2')->nullable();

            $table->boolean('is_default_delivery')->default(false);
            $table->boolean('is_default_billing')->default(false);

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('user_id')
                ->references('id')->on('identity.users')
                ->onDelete('restrict');
        });

        // Listings hot-path: bir kullanıcının tüm aktif adresleri
        DB::statement(
            'CREATE INDEX idx_addresses_user_id ON identity.addresses (user_id) '
            . 'WHERE deleted_at IS NULL'
        );

        // Bir kullanıcıda en fazla 1 aktif default delivery — DB invariant
        DB::statement(
            'CREATE UNIQUE INDEX uniq_addresses_default_delivery '
            . 'ON identity.addresses (user_id) '
            . 'WHERE is_default_delivery = true AND deleted_at IS NULL'
        );

        // Bir kullanıcıda en fazla 1 aktif default billing — DB invariant
        DB::statement(
            'CREATE UNIQUE INDEX uniq_addresses_default_billing '
            . 'ON identity.addresses (user_id) '
            . 'WHERE is_default_billing = true AND deleted_at IS NULL'
        );

        // CHECK: country code şu an yalnızca TR — multi-country migration'a
        // kadar diğer ülkeler reject. ALTER ile gevşetilir.
        DB::statement(
            "ALTER TABLE identity.addresses ADD CONSTRAINT chk_addresses_country "
            . "CHECK (country_code = 'TR')"
        );

        // CHECK: phone karakter set'i — VO normalize sonrası +E164 garantili
        // ama bu satır defansif (DB seed/admin import için).
        DB::statement(
            "ALTER TABLE identity.addresses ADD CONSTRAINT chk_addresses_phone "
            . "CHECK (phone ~ '^[+0-9 ()\\-]+$')"
        );

        // CHECK: address_line min uzunluk (uygulama tarafı da kontrol eder
        // ama defansif — DB seed bypass için)
        DB::statement(
            "ALTER TABLE identity.addresses ADD CONSTRAINT chk_addresses_address_line_length "
            . "CHECK (char_length(address_line) >= 10)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.addresses');
    }
};
