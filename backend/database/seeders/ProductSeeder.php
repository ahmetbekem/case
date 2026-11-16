<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\System\ValueObjects\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Identity\Models\UserModel;

/**
 * Demo ürünler — admin tarafından yaratılmış gibi.
 *
 * ADR-016 Single Base Currency: tüm fiyatlar system base_currency'de
 * (default TRY) saklanır. Eski mixed-currency seed'i (USD/EUR karışımı)
 * bu sürümde tek currency'ye çevrildi; yaklaşık konversiyonla TRY
 * değerleri yazıldı (gerçek mağaza fiyatlarını yansıtır, demo amaçlı).
 *
 * Currency column'u settings'ten okunan base_currency'ye atanır —
 * admin sonradan base'i değiştirirse yeni seeder run o currency'de
 * yazar (mevcut ürünleri ezmez, updateOrCreate by SKU).
 */
final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = UserModel::query()->where('email', 'admin@example.test')->first();
        if ($admin === null) {
            $this->command?->warn('[products] admin@example.test bulunamadı — UserSeeder önce çalıştırılmalı');
            return;
        }
        $createdBy = (string) $admin->id;

        $baseCurrency = (string) (DB::table('system.settings')
            ->where('key', SettingKey::BASE_CURRENCY)
            ->value('value') ?? 'TRY');

        foreach (self::data() as $row) {
            ProductModel::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'id' => ProductModel::query()->where('sku', $row['sku'])->value('id') ?? Str::uuid()->toString(),
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'currency' => $baseCurrency,
                    'stock' => $row['stock'],
                    'is_active' => true,
                    'created_by' => $createdBy,
                ],
            );
        }

        $this->command?->info(
            '[products] seeded: ' . count(self::data()) . ' SKU @ ' . $baseCurrency,
        );
    }

    /**
     * @return array<int, array{sku:string,name:string,description:string,price:float,stock:int}>
     */
    private static function data(): array
    {
        return [
            // ─── Giyim (3) ───
            [
                'sku' => 'CLOTH-TSHIRT-BLACK-M',
                'name' => 'Klasik Siyah Tişört (M)',
                'description' => '%100 organik pamuk, regular fit. 30 derece yıkama. Türkiye\'de üretildi.',
                'price' => 249.90,
                'stock' => 120,
            ],
            [
                'sku' => 'CLOTH-HOODIE-NAVY-L',
                'name' => 'Lacivert Kapüşonlu Sweatshirt (L)',
                'description' => 'Polar iç astar, kanguru cep, fermuarsız. İlkbahar/sonbahar için ideal.',
                'price' => 599.00,
                'stock' => 45,
            ],
            [
                'sku' => 'CLOTH-JEANS-SLIM-32',
                'name' => 'Slim Fit Kot Pantolon (32/32)',
                'description' => '%98 pamuk %2 likra, dar kesim, indigo yıkama. Beş cep tasarımı.',
                'price' => 849.00,
                'stock' => 32,
            ],
            // ─── Ayakkabı (2) — eski USD/EUR fiyatlar TRY'ye yaklaşık çevrildi ───
            [
                'sku' => 'SHOE-SNEAKER-WHITE-42',
                'name' => 'Beyaz Spor Ayakkabı (42)',
                'description' => 'Hafif EVA taban, hava alabilen mesh üst yüzey. Şehir içi günlük kullanım.',
                'price' => 3499.00,
                'stock' => 60,
            ],
            [
                'sku' => 'SHOE-BOOT-BROWN-43',
                'name' => 'Kahverengi Deri Bot (43)',
                'description' => 'Gerçek dana derisi, kauçuk taban, su itici işlem. Kışa hazırlık.',
                'price' => 4299.00,
                'stock' => 18,
            ],
            // ─── Elektronik (3) ───
            [
                'sku' => 'TECH-HEADPHONE-BT',
                'name' => 'Kablosuz Bluetooth Kulaklık',
                'description' => 'Aktif gürültü engelleme, 30 saat batarya, USB-C şarj, hafif tasarım.',
                'price' => 5899.00,
                'stock' => 75,
            ],
            [
                'sku' => 'TECH-MOUSE-WIRELESS',
                'name' => 'Kablosuz Mouse',
                'description' => 'AAA pille 18 ay kullanım, 2.4 GHz dongle, ergonomik gövde.',
                'price' => 449.00,
                'stock' => 200,
            ],
            [
                'sku' => 'TECH-KEYBOARD-MECH',
                'name' => 'Mekanik Klavye RGB',
                'description' => 'Mavi switch, anti-ghosting, programlanabilir RGB aydınlatma. TR Q düzen.',
                'price' => 6599.00,
                'stock' => 26,
            ],
            // ─── Kitap (2) ───
            [
                'sku' => 'BOOK-DDD-EVANS',
                'name' => 'Domain-Driven Design — Eric Evans',
                'description' => 'Yazılım mimarisinin klasiği. İngilizce orijinal baskı, ciltli, 560 sayfa.',
                'price' => 1899.00,
                'stock' => 14,
            ],
            [
                'sku' => 'BOOK-CLEAN-ARCH',
                'name' => 'Clean Architecture — Robert C. Martin',
                'description' => 'Yazılım katmanlama prensipleri. İngilizce, 432 sayfa.',
                'price' => 379.00,
                'stock' => 22,
            ],
            // ─── Ev / Yaşam (2) ───
            [
                'sku' => 'HOME-MUG-CERAMIC',
                'name' => 'Seramik Kupa 350ml',
                'description' => 'Bulaşık makinesinde yıkanabilir, mikrodalga güvenli. Mat siyah.',
                'price' => 89.90,
                'stock' => 0,
            ],
            [
                'sku' => 'HOME-NOTEBOOK-A5',
                'name' => 'A5 Çizgili Defter — 200 sayfa',
                'description' => 'Sert kapak, ipek imli, 80gsm krem kağıt. Geri dönüştürülmüş malzeme.',
                'price' => 459.00,
                'stock' => 88,
            ],
            // ─── Aksesuar (3 yeni) ───
            [
                'sku' => 'ACC-SUNGLASSES-AVI',
                'name' => 'Aviator Güneş Gözlüğü',
                'description' => 'UV400 korumalı, paslanmaz çelik çerçeve, polarize cam. Klasik altın renk.',
                'price' => 1299.00,
                'stock' => 35,
            ],
            [
                'sku' => 'ACC-BACKPACK-25L',
                'name' => 'Sırt Çantası 25L',
                'description' => 'Su geçirmez kumaş, 15" laptop bölmesi, ergonomik askılar. Günlük + seyahat.',
                'price' => 1799.00,
                'stock' => 40,
            ],
            [
                'sku' => 'ACC-SCARF-WOOL',
                'name' => 'Yün Atkı Bordo',
                'description' => '%100 yün, 180×30cm, kışlık. Yumuşak dokulu, sıcak tutar.',
                'price' => 549.00,
                'stock' => 50,
            ],
        ];
    }
}
