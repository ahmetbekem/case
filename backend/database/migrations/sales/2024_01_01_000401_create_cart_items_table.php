<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales.cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->string('currency_snapshot', 3);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            // Cascade — when a cart is deleted, its items go with it
            $table->foreign('cart_id')
                ->references('id')->on('sales.carts')
                ->onDelete('cascade');

            // Restrict — cannot delete a product that is in someone's cart
            $table->foreign('product_id')
                ->references('id')->on('catalog.products')
                ->onDelete('restrict');

            // Same product cannot appear twice in a cart — bump quantity instead
            $table->unique(['cart_id', 'product_id'], 'uniq_cart_items_cart_product');

            $table->index('cart_id', 'idx_cart_items_cart_id');
            $table->index('product_id', 'idx_cart_items_product_id');
        });

        DB::statement('ALTER TABLE sales.cart_items ADD CONSTRAINT chk_cart_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales.cart_items ADD CONSTRAINT chk_cart_items_price CHECK (unit_price_snapshot >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.cart_items');
    }
};
