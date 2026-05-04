<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales.order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestampTz('created_at')->useCurrent();
            // No updated_at — order items are immutable

            // Cascade — order deletion takes its items
            $table->foreign('order_id')
                ->references('id')->on('sales.orders')
                ->onDelete('cascade');

            // Restrict — cannot delete a product that has been ordered
            $table->foreign('product_id')
                ->references('id')->on('catalog.products')
                ->onDelete('restrict');

            $table->index('order_id', 'idx_order_items_order_id');
            $table->index('product_id', 'idx_order_items_product_id');
        });

        DB::statement('ALTER TABLE sales.order_items ADD CONSTRAINT chk_order_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales.order_items ADD CONSTRAINT chk_order_items_amounts CHECK (unit_price >= 0 AND line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.order_items');
    }
};
