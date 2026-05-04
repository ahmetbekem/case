<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Sequence for human-readable order numbers: ORD-YYYY-000001
        DB::statement('CREATE SEQUENCE IF NOT EXISTS sales.order_number_seq START 1 INCREMENT 1');

        Schema::create('sales.orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 20);
            $table->uuid('user_id');
            $table->string('status', 32)->default('pending');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('display_currency', 3);
            $table->decimal('display_total', 15, 2);
            $table->decimal('exchange_rate_snapshot', 20, 8);
            $table->text('notes')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancelled_reason', 255)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('user_id')
                ->references('id')->on('identity.users')
                ->onDelete('restrict');

            $table->unique('order_number', 'uniq_orders_order_number');
        });

        DB::statement("ALTER TABLE sales.orders ADD CONSTRAINT chk_orders_status CHECK (status IN ('pending', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE sales.orders ADD CONSTRAINT chk_orders_amounts CHECK (subtotal >= 0 AND total >= 0 AND display_total >= 0)');
        DB::statement('ALTER TABLE sales.orders ADD CONSTRAINT chk_orders_exchange_rate CHECK (exchange_rate_snapshot > 0)');
        DB::statement("ALTER TABLE sales.orders ADD CONSTRAINT chk_orders_display_currency CHECK (display_currency IN ('TRY', 'USD', 'EUR'))");

        // Hot-path composite — "user X's orders, newest first"
        DB::statement('CREATE INDEX idx_orders_user_id_created_at ON sales.orders (user_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_orders_status ON sales.orders (status)');
        DB::statement('CREATE INDEX idx_orders_created_at ON sales.orders (created_at DESC)');
        DB::statement('CREATE INDEX idx_orders_user_id_status ON sales.orders (user_id, status)');

        // Now that sales.orders exists, complete the deferred FK on sales.carts
        DB::statement('ALTER TABLE sales.carts ADD CONSTRAINT fk_carts_converted_to_order_id FOREIGN KEY (converted_to_order_id) REFERENCES sales.orders(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales.carts DROP CONSTRAINT IF EXISTS fk_carts_converted_to_order_id');
        Schema::dropIfExists('sales.orders');
        DB::statement('DROP SEQUENCE IF EXISTS sales.order_number_seq');
    }
};
