<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Identity\Models\UserModel;

/**
 * ADR-018 — Demo adresleri.
 *
 * Idempotent: hem customer'a (Ev + İş) hem admin'e (Ofis) tek adres.
 * Mevcutsa user başına soft-delete edilmemiş kayıt sayısı kontrol
 * edilip yine de yeniden eklenmez (UI'dan eklenenler korunur).
 *
 * UserSeeder'dan SONRA çalışır (DatabaseSeeder ordering).
 */
final class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $customer = UserModel::query()->where('email', 'customer@dehasoft.test')->first();
        $admin = UserModel::query()->where('email', 'admin@dehasoft.test')->first();

        if ($customer !== null) {
            $this->seedFor($customer->id, [
                [
                    'title' => 'Ev',
                    'full_name' => 'Demo Customer',
                    'phone' => '+905551234567',
                    'city' => 'İstanbul',
                    'district' => 'Kadıköy',
                    'neighborhood' => 'Caferağa',
                    'postal_code' => '34710',
                    'address_line' => 'Moda Caddesi No: 123 Daire: 5',
                    'is_default_delivery' => true,
                    'is_default_billing' => true,
                ],
                [
                    'title' => 'İş',
                    'full_name' => 'Demo Customer',
                    'phone' => '+902125550123',
                    'city' => 'İstanbul',
                    'district' => 'Şişli',
                    'neighborhood' => 'Mecidiyeköy',
                    'postal_code' => '34387',
                    'address_line' => 'Büyükdere Caddesi No: 100 Kat: 5',
                    'is_default_delivery' => false,
                    'is_default_billing' => false,
                ],
            ]);
        }

        if ($admin !== null) {
            $this->seedFor($admin->id, [
                [
                    'title' => 'Ofis',
                    'full_name' => 'Demo Admin',
                    'phone' => '+903123334455',
                    'city' => 'Ankara',
                    'district' => 'Çankaya',
                    'neighborhood' => 'Kavaklıdere',
                    'postal_code' => '06700',
                    'address_line' => 'Atatürk Bulvarı No: 45 Kat: 3',
                    'is_default_delivery' => true,
                    'is_default_billing' => true,
                ],
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $addresses
     */
    private function seedFor(string $userId, array $addresses): void
    {
        $existing = DB::table('identity.addresses')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->count();
        if ($existing > 0) {
            $this->command?->info("[addresses] user {$userId} already has {$existing} address(es); skipping seed");
            return;
        }

        $rows = [];
        foreach ($addresses as $a) {
            $rows[] = array_merge($a, [
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'country_code' => 'TR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('identity.addresses')->insert($rows);
        $this->command?->info('[addresses] seeded: ' . count($rows) . " address(es) for {$userId}");
    }
}
