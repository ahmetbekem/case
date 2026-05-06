<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * system.settings — basit key-value ayar deposu. value TEXT (json/string mix).
 * type kolonu okuma/parse hint'i için (string|number|boolean|json). is_public
 * true ise auth gerektirmeyen public endpoint'ten okunabilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('system.settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('type', 20)->default('string');
            $table->string('category', 50)->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        DB::statement(
            "ALTER TABLE system.settings ADD CONSTRAINT chk_settings_type "
            . "CHECK (type IN ('string', 'number', 'boolean', 'json'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('system.settings');
    }
};
