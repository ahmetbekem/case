<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Identity\Models\UserModel;

/**
 * Demo ürünler — admin kullanıcı tarafından yaratılmış gibi görünür.
 * Currency switcher demo'su için TRY/USD/EUR karışımı.
 *
 * SKU üzerinden updateOrCreate — idempotent, tekrar çalıştırılınca
 * yeni satır oluşturmaz, mevcut alanları günceller.
 */
final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = UserModel::query()->where('email', 'admin@dehasoft.test')->first();
        if ($admin === null) {
            $this->command?->warn('[products] admin@dehasoft.test bulunamadı — UserSeeder önce çalıştırılmalı');
            return;
        }
        $createdBy = (string) $admin->id;

        foreach (self::data() as $row) {
            ProductModel::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'id' => ProductModel::query()->where('sku', $row['sku'])->value('id') ?? Str::uuid()->toString(),
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'currency' => $row['currency'],
                    'stock' => $row['stock'],
                    'is_active' => true,
                    'created_by' => $createdBy,
                ],
            );
        }

        $this->command?->info('[products] seeded: ' . count(self::data()) . ' SKU');
    }

    /**
     * @return array<int, array{sku:string,name:string,description:string,price:float,currency:string,stock:int}>
     */
    private static function data(): array
    {
        return [
            // ─── Giyim ───
            [
                'sku' => 'CLOTH-TSHIRT-BLACK-M',
                'name' => 'Klasik Siyah Tişört (M)',
                'description' => '%100 organik pamuk, regular fit. 30 derece yıkama. Türkiye\'de üretildi.',
                'price' => 249.90,
                'currency' => 'TRY',
                'stock' => 120,
            ],
            [
                'sku' => 'CLOTH-HOODIE-NAVY-L',
                'name' => 'Lacivert Kapüşonlu Sweatshirt (L)',
                'description' => 'Polar iç astar, kanguru cep, fermuarsız. İlkbahar/sonbahar için ideal.',
                'price' => 599.00,
                'currency' => 'TRY',
                'stock' => 45,
            ],
            [
                'sku' => 'CLOTH-JEANS-SLIM-32',
                'name' => 'Slim Fit Kot Pantolon (32/32)',
                'description' => '%98 pamuk %2 likra, dar kesim, indigo yıkama. Beş cep tasarımı.',
                'price' => 849.00,
                'currency' => 'TRY',
                'stock' => 32,
            ],
            // ─── Ayakkabı ───
            [
                'sku' => 'SHOE-SNEAKER-WHITE-42',
                'name' => 'Beyaz Spor Ayakkabı (42)',
                'description' => 'Hafif EVA taban, hava alabilen mesh üst yüzey. Şehir içi günlük kullanım.',
                'price' => 89.90,
                'currency' => 'USD',
                'stock' => 60,
            ],
            [
                'sku' => 'SHOE-BOOT-BROWN-43',
                'name' => 'Kahverengi Deri Bot (43)',
                'description' => 'Gerçek dana derisi, kauçuk taban, su itici işlem. Kışa hazırlık.',
                'price' => 129.00,
                'currency' => 'EUR',
                'stock' => 18,
            ],
            // ─── Elektronik ───
            [
                'sku' => 'TECH-HEADPHONE-BT',
                'name' => 'Kablosuz Bluetooth Kulaklık',
                'description' => 'Aktif gürültü engelleme, 30 saat batarya, USB-C şarj, hafif tasarım.',
                'price' => 149.00,
                'currency' => 'USD',
                'stock' => 75,
            ],
            [
                'sku' => 'TECH-MOUSE-WIRELESS',
                'name' => 'Kablosuz Mouse',
                'description' => 'AAA pille 18 ay kullanım, 2.4 GHz dongle, ergonomik gövde.',
                'price' => 449.00,
                'currency' => 'TRY',
                'stock' => 200,
            ],
            [
                'sku' => 'TECH-KEYBOARD-MECH',
                'name' => 'Mekanik Klavye RGB',
                'description' => 'Mavi switch, anti-ghosting, programlanabilir RGB aydınlatma. TR Q düzen.',
                'price' => 199.00,
                'currency' => 'EUR',
                'stock' => 26,
            ],
            // ─── Kitap ───
            [
                'sku' => 'BOOK-DDD-EVANS',
                'name' => 'Domain-Driven Design — Eric Evans',
                'description' => 'Yazılım mimarisinin klasiği. İngilizce orijinal baskı, ciltli, 560 sayfa.',
                'price' => 49.90,
                'currency' => 'USD',
                'stock' => 14,
            ],
            [
                'sku' => 'BOOK-CLEAN-ARCH',
                'name' => 'Clean Architecture — Robert C. Martin',
                'description' => 'Yazılım katmanlama prensipleri. İngilizce, 432 sayfa.',
                'price' => 379.00,
                'currency' => 'TRY',
                'stock' => 22,
            ],
            // ─── Ev / Yaşam ───
            [
                'sku' => 'HOME-MUG-CERAMIC',
                'name' => 'Seramik Kupa 350ml',
                'description' => 'Bulaşık makinesinde yıkanabilir, mikrodalga güvenli. Mat siyah.',
                'price' => 89.90,
                'currency' => 'TRY',
                'stock' => 0,
            ],
            [
                'sku' => 'HOME-NOTEBOOK-A5',
                'name' => 'A5 Çizgili Defter — 200 sayfa',
                'description' => 'Sert kapak, ipek imli, 80gsm krem kağıt. Geri dönüştürülmüş malzeme.',
                'price' => 12.50,
                'currency' => 'EUR',
                'stock' => 88,
            ],
        ];
    }
}
