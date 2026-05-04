<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Module-scoped migration paths. Laravel auto-loads
        // database/migrations/ but ignores its sub-directories,
        // so each module folder is registered explicitly.
        $this->loadMigrationsFrom([
            database_path('migrations/0000_setup'),
            database_path('migrations/identity'),
            database_path('migrations/catalog'),
            database_path('migrations/currency'),
            database_path('migrations/sales'),
        ]);
    }
}
