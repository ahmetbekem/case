<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog.products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('TRY');
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('created_by')
                ->references('id')->on('identity.users')
                ->onDelete('restrict');
        });

        DB::statement('ALTER TABLE catalog.products ADD CONSTRAINT chk_products_price CHECK (price >= 0)');
        DB::statement('ALTER TABLE catalog.products ADD CONSTRAINT chk_products_stock CHECK (stock >= 0)');
        DB::statement("ALTER TABLE catalog.products ADD CONSTRAINT chk_products_currency CHECK (currency IN ('TRY', 'USD', 'EUR'))");

        // Partial unique index — SKU only unique among non-soft-deleted rows
        DB::statement('CREATE UNIQUE INDEX uniq_products_sku ON catalog.products (sku) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_products_is_active ON catalog.products (is_active) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_products_created_at ON catalog.products (created_at DESC)');

        // Trigram GIN index for fuzzy product name search (requires pg_trgm extension)
        DB::statement('CREATE INDEX idx_products_name_trgm ON catalog.products USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog.products');
    }
};
