<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `system` şeması — system_wide ayarlar (base_currency, supported_currencies vb.).
 * Ayrı migration olarak tutuldu çünkü 0000_setup/000000 mevcut prod DB'lerde
 * çalıştırılmış olabilir; oraya schema eklemek idempotent değil.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS system');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS system CASCADE');
    }
};
