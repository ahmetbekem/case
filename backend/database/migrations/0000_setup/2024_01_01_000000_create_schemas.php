<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS identity');
        DB::statement('CREATE SCHEMA IF NOT EXISTS catalog');
        DB::statement('CREATE SCHEMA IF NOT EXISTS sales');
        DB::statement('CREATE SCHEMA IF NOT EXISTS currency');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS currency CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS sales CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS catalog CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS identity CASCADE');
    }
};
