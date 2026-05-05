<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters — ProductSeeder admin user'ın id'sini created_by için kullanır
        $this->call(UserSeeder::class);
        $this->call(ProductSeeder::class);
    }
}
