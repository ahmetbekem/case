<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Currency\Models\ExchangeRateModel;
use Infrastructure\Sales\Models\CartModel;
use Infrastructure\System\Models\SettingModel;
use Ramsey\Uuid\Uuid;

/**
 * ADR-016 — base_currency PATCH AdminSettingsController içinde
 * ChangeBaseCurrencyHandler'a yönlendirilir. Bu suite handler
 * davranışlarını HTTP üzerinden exercise eder: products repriceall,
 * aktif cart abandoned, no-op idempotent, kur eksik/stale 422/404,
 * yetkilendirme.
 */

function seedBaseCurrencySetting(string $value = 'TRY'): void
{
    SettingModel::query()->insert([
        'id' => Uuid::uuid4()->toString(),
        'key' => 'base_currency',
        'value' => $value,
        'type' => 'string',
        'category' => 'currency',
        'description' => 'Sistem ana para birimi',
        'is_public' => true,
        'created_at' => now(),
    ]);
}

// seedRate name çakışıyor (Currency/RatesTest), suffix-li helper.
function seedExchangeRateForBaseChange(string $base, string $target, float $rate, ?\DateTimeInterface $date = null): void
{
    ExchangeRateModel::query()->insert([
        'id' => Uuid::uuid4()->toString(),
        'base_currency' => $base,
        'target_currency' => $target,
        'rate' => $rate,
        'rate_date' => ($date ?? now())->format('Y-m-d'),
        'fetched_at' => now(),
        'provider' => 'test',
        'created_at' => now(),
    ]);
}

function seedProductDirect(string $sku, float $price, string $currency = 'TRY'): string
{
    // FK: catalog.products.created_by → identity.users.id. Test sırasında
    // herhangi bir mevcut admin'i kullanırız; yoksa yenisini seed ederiz.
    $createdBy = \Infrastructure\Identity\Models\UserModel::query()->value('id');
    if ($createdBy === null) {
        $admin = seedUserForSettings(role: 'admin');
        $createdBy = $admin['id'];
    }
    $id = Uuid::uuid4()->toString();
    ProductModel::query()->insert([
        'id' => $id,
        'sku' => $sku,
        'name' => 'Test ' . $sku,
        'description' => null,
        'price' => $price,
        'currency' => $currency,
        'stock' => 10,
        'is_active' => true,
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return $id;
}

function adminToken(): string
{
    $admin = seedUserForSettings(role: 'admin');
    return loginAndGetToken($admin);
}

describe('PATCH /admin/system/settings/base_currency — ADR-016 reprice flow', function () {
    it('reprices all products from old base to new base using the latest rate', function () {
        seedBaseCurrencySetting('TRY');
        seedExchangeRateForBaseChange('TRY', 'USD', 0.05);

        seedProductDirect('P-1', 100.00, 'TRY');
        seedProductDirect('P-2', 250.00, 'TRY');
        seedProductDirect('P-3', 80.00, 'TRY');

        $response = test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertOk();

        expect($response->json('data.fromCurrency'))->toBe('TRY')
            ->and($response->json('data.toCurrency'))->toBe('USD')
            ->and($response->json('data.rate'))->toBe(0.05)
            ->and($response->json('data.productsUpdated'))->toBe(3)
            ->and($response->json('message'))->toBe('Base currency changed; products repriced');

        // Setting güncellendi
        $setting = SettingModel::query()->where('key', 'base_currency')->first();
        expect($setting->value)->toBe('USD');

        // Ürünler USD'ye çevrildi: 100 → 5.00, 250 → 12.50, 80 → 4.00
        $prices = ProductModel::query()
            ->orderBy('sku')
            ->pluck('price', 'sku')
            ->all();
        expect((float) $prices['P-1'])->toBe(5.00)
            ->and((float) $prices['P-2'])->toBe(12.50)
            ->and((float) $prices['P-3'])->toBe(4.00);

        // Currency kolonu da güncellendi
        $currencies = ProductModel::query()->pluck('currency')->unique()->values()->all();
        expect($currencies)->toBe(['USD']);
    });

    it('uses inverse rate when only (new → old) exists', function () {
        seedBaseCurrencySetting('TRY');
        // Sadece USD → TRY var; USD'ye çevirmek için 1/30 = 0.0333... lazım
        seedExchangeRateForBaseChange('USD', 'TRY', 30.0);
        seedProductDirect('INV-1', 90.00, 'TRY');

        $response = test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertOk();

        expect($response->json('data.rate'))->toBeGreaterThan(0.033)
            ->toBeLessThan(0.034);
        $price = (float) ProductModel::query()->where('sku', 'INV-1')->value('price');
        expect($price)->toBeGreaterThan(2.99)->toBeLessThan(3.01); // 90 / 30 ≈ 3
    });

    it('abandons all active carts to prevent mixed-currency snapshots', function () {
        seedBaseCurrencySetting('TRY');
        seedExchangeRateForBaseChange('TRY', 'USD', 0.05);
        seedProductDirect('CART-1', 100.00, 'TRY');

        // 2 aktif cart yarat
        CartModel::query()->insert([
            [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => null,
                'guest_session_id' => Uuid::uuid4()->toString(),
                'status' => 'active',
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => null,
                'guest_session_id' => Uuid::uuid4()->toString(),
                'status' => 'active',
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertOk();

        expect($response->json('data.cartsAbandoned'))->toBe(2);
        $statuses = CartModel::query()->pluck('status')->unique()->values()->all();
        expect($statuses)->toBe(['abandoned']);
    });

    it('is a no-op when target equals current base (idempotent)', function () {
        seedBaseCurrencySetting('TRY');
        seedProductDirect('NOOP-1', 100.00, 'TRY');

        $response = test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'TRY'])
            ->assertOk();

        expect($response->json('data.productsUpdated'))->toBe(0)
            ->and($response->json('data.cartsAbandoned'))->toBe(0)
            ->and((float) ProductModel::query()->where('sku', 'NOOP-1')->value('price'))->toBe(100.00);
    });

    it('returns 404 when no exchange rate is available for the pair', function () {
        seedBaseCurrencySetting('TRY');
        // Hiç kur seedlenmedi
        seedProductDirect('NO-RATE-1', 100.00, 'TRY');

        test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertNotFound()
            ->assertJson(['error' => ['code' => 'EXCHANGE_RATE_NOT_FOUND']]);

        // Rollback: ne setting ne ürün değişti
        $setting = SettingModel::query()->where('key', 'base_currency')->first();
        expect($setting->value)->toBe('TRY');
        expect((float) ProductModel::query()->where('sku', 'NO-RATE-1')->value('price'))->toBe(100.00);
    });

    it('returns 409 when the latest rate is older than 7 days', function () {
        seedBaseCurrencySetting('TRY');
        seedExchangeRateForBaseChange('TRY', 'USD', 0.05, now()->subDays(10));
        seedProductDirect('STALE-1', 100.00, 'TRY');

        test()->withHeader('Authorization', 'Bearer ' . adminToken())
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertStatus(409)
            ->assertJson(['error' => ['code' => 'STALE_EXCHANGE_RATE']]);

        // Rollback
        expect((float) ProductModel::query()->where('sku', 'STALE-1')->value('price'))->toBe(100.00);
    });

    it('rejects non-admin callers with 403', function () {
        seedBaseCurrencySetting('TRY');
        $customer = seedUserForSettings(role: 'customer');
        $token = loginAndGetToken($customer);

        test()->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertForbidden();
    });
});
