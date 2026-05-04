<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales.carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('status', 32)->default('active');
            // converted_to_order_id FK is added later by the orders migration,
            // since sales.orders does not exist yet at this point.
            $table->uuid('converted_to_order_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('user_id')
                ->references('id')->on('identity.users')
                ->onDelete('restrict');

            $table->index('user_id', 'idx_carts_user_id');
            $table->index('status', 'idx_carts_status');
        });

        DB::statement("ALTER TABLE sales.carts ADD CONSTRAINT chk_carts_status CHECK (status IN ('active', 'converted', 'abandoned'))");

        // CRITICAL: enforce one active cart per user via partial unique index
        DB::statement("CREATE UNIQUE INDEX uniq_carts_user_active ON sales.carts (user_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('sales.carts');
    }
};
