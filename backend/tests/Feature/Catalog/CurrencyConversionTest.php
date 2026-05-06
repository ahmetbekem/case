<?php

declare(strict_types=1);

use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Currency\Models\ExchangeRateModel;
use Infrastructure\Identity\Models\UserModel;
use Infrastructure\System\Models\SettingModel;
use Ramsey\Uuid\Uuid;

/**
 * ADR-016 — runtime currency conversion. Backend product list/get
 * endpoint'leri ?displayCurrency=X parametresiyle çağrıldığında
 * basePrice/baseCurrency koruyup displayPrice/displayCurrency/exchangeRate
 * alanlarını dolduruyor mu doğrular.
 */

function seedSystemBaseCurrency(string $code = 'TRY'): void
{
    SettingModel::query()->updateOrInsert(
        ['key' => 'base_currency'],
        [
            'id' => Uuid::uuid4()->toString(),
            'key' => 'base_currency',
            'value' => $code,
            'type' => 'string',
            'category' => 'currency',
            'is_public' => true,
            'created_at' => now(),
        ],
    );
}

function seedAdminForConversion(): UserModel
{
    $u = new UserModel();
    $u->id = Uuid::uuid4()->toString();
    $u->email = 'admin-conv-' . uniqid() . '@test';
    $u->password_hash = bcrypt('x');
    $u->full_name = 'Conv Admin';
    $u->role = 'admin';
    $u->is_active = true;
    $u->created_at = now();
    $u->save();
    return $u;
}

function seedTryProductForConversion(string $sku, float $price, string $admin): string
{
    $id = Uuid::uuid4()->toString();
    ProductModel::query()->insert([
        'id' => $id,
        'sku' => $sku,
        'name' => 'Conv ' . $sku,
        'description' => 'fx test',
        'price' => $price,
        'currency' => 'TRY',
        'stock' => 10,
        'is_active' => true,
        'created_by' => $admin,
        'created_at' => now(),
    ]);
    return $id;
}

function seedRateForConversion(string $base, string $target, float $rate): void
{
    ExchangeRateModel::query()->insert([
        'id' => Uuid::uuid4()->toString(),
        'base_currency' => $base,
        'target_currency' => $target,
        'rate' => $rate,
        'rate_date' => date('Y-m-d'),
        'fetched_at' => now(),
        'provider' => 'frankfurter',
        'created_at' => now(),
    ]);
}

describe('GET /catalog/products?displayCurrency', function () {
    it('returns identity (display fields null) when displayCurrency == baseCurrency', function () {
        seedSystemBaseCurrency('TRY');
        $admin = seedAdminForConversion();
        seedTryProductForConversion('CONV-IDENT-1', 100.00, (string) $admin->id);

        $response = test()->getJson('/api/v1/catalog/products?displayCurrency=TRY')
            ->assertOk();

        $product = collect($response->json('data'))
            ->firstWhere('sku', 'CONV-IDENT-1');

        expect($product)->not->toBeNull()
            ->and($product['basePrice'])->toEqual(100.00)
            ->and($product['baseCurrency'])->toBe('TRY')
            ->and($product['displayPrice'])->toBeNull()
            ->and($product['displayCurrency'])->toBeNull()
            ->and($product['exchangeRate'])->toBeNull();
    });

    it('converts price to displayCurrency when rate available', function () {
        seedSystemBaseCurrency('TRY');
        $admin = seedAdminForConversion();
        seedTryProductForConversion('CONV-USD-1', 1000.00, (string) $admin->id);
        seedRateForConversion('TRY', 'USD', 0.05);

        $response = test()->getJson('/api/v1/catalog/products?displayCurrency=USD')
            ->assertOk();

        $product = collect($response->json('data'))
            ->firstWhere('sku', 'CONV-USD-1');

        expect($product)->not->toBeNull()
            ->and($product['basePrice'])->toEqual(1000.00)
            ->and($product['baseCurrency'])->toBe('TRY')
            ->and($product['displayPrice'])->toEqual(50.00)   // 1000 × 0.05
            ->and($product['displayCurrency'])->toBe('USD')
            ->and($product['exchangeRate'])->toBe(0.05);
    });

    it('falls back to base when rate missing (no error, display fields null)', function () {
        seedSystemBaseCurrency('TRY');
        $admin = seedAdminForConversion();
        seedTryProductForConversion('CONV-EUR-1', 500.00, (string) $admin->id);
        // EUR rate kasıtlı seed edilmedi

        $response = test()->getJson('/api/v1/catalog/products?displayCurrency=EUR')
            ->assertOk();

        $product = collect($response->json('data'))
            ->firstWhere('sku', 'CONV-EUR-1');

        expect($product)->not->toBeNull()
            ->and($product['basePrice'])->toEqual(500.00)
            ->and($product['baseCurrency'])->toBe('TRY')
            ->and($product['displayPrice'])->toBeNull()
            ->and($product['displayCurrency'])->toBeNull();
    });
});

describe('GET /catalog/products/{id}?displayCurrency', function () {
    it('single product converts identically to list endpoint', function () {
        seedSystemBaseCurrency('TRY');
        $admin = seedAdminForConversion();
        $id = seedTryProductForConversion('CONV-DETAIL-1', 250.00, (string) $admin->id);
        seedRateForConversion('TRY', 'USD', 0.04);

        $response = test()->getJson("/api/v1/catalog/products/{$id}?displayCurrency=USD")
            ->assertOk();

        expect($response->json('data.basePrice'))->toEqual(250.00)
            ->and($response->json('data.baseCurrency'))->toBe('TRY')
            ->and($response->json('data.displayPrice'))->toEqual(10.00)   // 250 × 0.04
            ->and($response->json('data.displayCurrency'))->toBe('USD')
            ->and($response->json('data.exchangeRate'))->toBe(0.04);
    });
});
