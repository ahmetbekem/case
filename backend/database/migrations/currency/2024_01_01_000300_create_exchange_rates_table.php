<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency.exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('base_currency', 3);
            $table->string('target_currency', 3);
            $table->decimal('rate', 20, 8);
            $table->date('rate_date');
            $table->timestampTz('fetched_at')->useCurrent();
            $table->string('provider', 64)->default('frankfurter');
            $table->timestampTz('created_at')->useCurrent();
            // No updated_at — exchange rates are immutable historical data
        });

        DB::statement('ALTER TABLE currency.exchange_rates ADD CONSTRAINT chk_exchange_rates_rate CHECK (rate > 0)');
        DB::statement("ALTER TABLE currency.exchange_rates ADD CONSTRAINT chk_exchange_rates_base_currency CHECK (base_currency IN ('TRY', 'USD', 'EUR'))");
        DB::statement("ALTER TABLE currency.exchange_rates ADD CONSTRAINT chk_exchange_rates_target_currency CHECK (target_currency IN ('TRY', 'USD', 'EUR'))");
        DB::statement('ALTER TABLE currency.exchange_rates ADD CONSTRAINT chk_exchange_rates_diff_currency CHECK (base_currency != target_currency)');

        // One rate per pair per day
        DB::statement('CREATE UNIQUE INDEX uniq_exchange_rates_pair_date ON currency.exchange_rates (base_currency, target_currency, rate_date)');
        // Hot-path index — "give me the latest rate for this pair"
        DB::statement('CREATE INDEX idx_exchange_rates_pair_date_desc ON currency.exchange_rates (base_currency, target_currency, rate_date DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('currency.exchange_rates');
    }
};
