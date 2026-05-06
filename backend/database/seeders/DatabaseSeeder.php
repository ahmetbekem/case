<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sıra önemli:
        // 1) System settings — base_currency vb. (sonraki seeder'lar okur)
        // 2) Users — admin (ProductSeeder admin id'sini created_by için kullanır)
        // 3) Products — base_currency'yi okumak için sırasıyla settings sonrası
        $this->call(SystemSettingsSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ProductSeeder::class);
    }
}
