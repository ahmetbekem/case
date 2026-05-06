<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\System\ValueObjects\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Sistem ayarlarının ilk değerleri. updateOrInsert by 'key' ile idempotent —
 * tekrar çalıştırınca aynı key'lerin value'sunu DEĞİŞTİRMEZ (yalnızca yoksa
 * yaratır). Admin /admin/system/settings/{key} ile değer güncellediği bir
 * ayarı seeder rerun ezmesin diye bu seçim.
 *
 * SettingKey constants ile senkron — yeni anahtar eklerken hem orada hem
 * burada güncel tut.
 */
final class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => SettingKey::BASE_CURRENCY,
                'value' => 'TRY',
                'type' => 'string',
                'category' => 'currency',
                'description' => 'Sistemin temel para birimi. Tüm ürün fiyatları bu para biriminde saklanır.',
                'is_public' => true,
            ],
            [
                'key' => SettingKey::SUPPORTED_CURRENCIES,
                'value' => json_encode(['TRY', 'USD', 'EUR']),
                'type' => 'json',
                'category' => 'currency',
                'description' => 'Görüntüleme için desteklenen para birimleri.',
                'is_public' => true,
            ],
            [
                'key' => SettingKey::DEFAULT_DISPLAY_CURRENCY,
                'value' => 'TRY',
                'type' => 'string',
                'category' => 'currency',
                'description' => 'Kullanıcı seçim yapmadığında varsayılan görüntüleme.',
                'is_public' => true,
            ],
            [
                'key' => SettingKey::SITE_NAME,
                'value' => 'Dehasoft Test Store',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Site adı (header, email subject, vs.).',
                'is_public' => true,
            ],
        ];

        $now = now();
        $inserted = 0;
        $skipped = 0;

        foreach ($defaults as $row) {
            $exists = DB::table('system.settings')->where('key', $row['key'])->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            DB::table('system.settings')->insert(array_merge($row, [
                'id' => Uuid::uuid4()->toString(),
                'created_at' => $now,
            ]));
            $inserted++;
        }

        $this->command?->info("[system.settings] inserted: {$inserted}, skipped (already present): {$skipped}");
    }
}
