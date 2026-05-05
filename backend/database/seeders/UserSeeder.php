<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Infrastructure\Identity\Models\UserModel;

/**
 * Demo hesapları — tekrar çalıştırılınca aynı email'leri günceller (updateOrCreate).
 *
 * Şifreler config/hashing.php aktif round'u ile bcrypt'lenir (testte BCRYPT_ROUNDS=4,
 * dev/prod default 12). Identity/PlainPassword min 8 karakter şartına uyar.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser(
            email: 'admin@dehasoft.test',
            password: 'Admin123!',
            fullName: 'Demo Admin',
            role: 'admin',
        );

        $this->seedUser(
            email: 'customer@dehasoft.test',
            password: 'Customer123!',
            fullName: 'Demo Customer',
            role: 'customer',
        );
    }

    private function seedUser(string $email, string $password, string $fullName, string $role): void
    {
        UserModel::query()->updateOrCreate(
            ['email' => $email],
            [
                // updateOrCreate yeni kayıtta id'yi atamaz; Postgres tarafında uuid
                // default'u yok (migration uuid()->primary() veriyor ama default expr yok).
                // Mevcut kayıt varsa id zaten dolu — DB::raw ile yeniden seti yapmıyoruz.
                'id' => UserModel::query()->where('email', $email)->value('id') ?? Str::uuid()->toString(),
                'password_hash' => Hash::make($password),
                'full_name' => $fullName,
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("[users] seeded: {$email} ({$role})");
    }
}
