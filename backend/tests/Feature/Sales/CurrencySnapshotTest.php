<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Infrastructure\Currency\Models\ExchangeRateModel;
use Ramsey\Uuid\Uuid;

/**
 * ADR-010 — Order anında dondurulmuş exchangeRateSnapshot ve
 * displayTotal, sonradan kur değişse bile sabit kalmalı. Code Review
 * v2 Phase 4.3'te tespit edildi: bu garanti yalnızca unit-level
 * (Order entity refuses non-positive snapshot) test'le doğrulanmıştı,
 * E2E senaryosu yoktu — biri OrderRepository hydrate'inden snapshot
 * okumayı bozsa hiçbir test yakalamayacaktı.
 */

function seedRateForSnapshot(string $base, string $target, float $rate): string
{
    $id = Uuid::uuid4()->toString();
    ExchangeRateModel::query()->insert([
        'id' => $id,
        'base_currency' => $base,
        'target_currency' => $target,
        'rate' => $rate,
        'rate_date' => now()->format('Y-m-d'),
        'fetched_at' => now(),
        'provider' => 'snapshot-test',
        'created_at' => now(),
    ]);
    return $id;
}

describe('Order currency snapshot — ADR-010 immutability', function () {
    it('exchangeRateSnapshot + displayTotal stay frozen after rate update', function () {
        // Setup: rate TRY → USD = 0.05 (1 USD = 20 TRY)
        $oldRateId = seedRateForSnapshot('TRY', 'USD', 0.05);

        $session = registerAndLoginCustomer('snapshot-imm@user.test');
        $productId = seedProductForOrders('SNAP-IMM', 100.0, 5);

        // Order: 2 × 100 TRY = 200 TRY → 200 × 0.05 = 10.00 USD
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 2],
            authHeaders($session['accessToken']),
        )->assertOk();

        $addressId = ensureAddressFor($session);
        $orderResp = test()->postJson(
            '/api/v1/sales/orders',
            ['displayCurrency' => 'USD', 'deliveryAddressId' => $addressId],
            authHeaders($session['accessToken']),
        )->assertCreated();

        $orderId = $orderResp->json('data.id');
        $originalRate = (float) $orderResp->json('data.exchangeRateSnapshot');
        $originalDisplay = (float) $orderResp->json('data.displayTotal');

        expect($originalRate)->toBe(0.05)
            ->and($originalDisplay)->toBe(10.00);

        // Rate'i değiştir. uniq_exchange_rates_pair_date constraint
        // aynı gün için ikinci satıra izin vermez; bu yüzden mevcut
        // rate'i UPDATE ederek "rate yenilendi" senaryosunu simule
        // ediyoruz. Snapshot'ın gerçekten dondurulmuş olduğunu test
        // etmek için yeterli.
        DB::table('currency.exchange_rates')->where('id', $oldRateId)->update(['rate' => 0.10]);

        // Order'ı GET → snapshot DEĞİŞMEMELİ
        $afterResp = test()->getJson(
            '/api/v1/sales/orders/' . $orderId,
            authHeaders($session['accessToken']),
        )->assertOk();

        expect((float) $afterResp->json('data.exchangeRateSnapshot'))->toBe(0.05) // ✓ snapshot
            ->and((float) $afterResp->json('data.displayTotal'))->toBe(10.00)     // ✓ snapshot
            ->and((float) $afterResp->json('data.subtotal'))->toBe(200.00)        // base aynı
            ->and($afterResp->json('data.displayCurrency'))->toBe('USD');
    });

    it('two orders placed on same day at different rates show different snapshots', function () {
        // İlk rate
        $rateId = seedRateForSnapshot('TRY', 'USD', 0.04);

        $session = registerAndLoginCustomer('snapshot-multi@user.test');
        $productId = seedProductForOrders('SNAP-MULTI', 100.0, 100);
        $addressId = ensureAddressFor($session);

        test()->postJson('/api/v1/sales/cart/items', ['productId' => $productId, 'quantity' => 1], authHeaders($session['accessToken']))->assertOk();
        $r1 = test()->postJson('/api/v1/sales/orders', ['displayCurrency' => 'USD', 'deliveryAddressId' => $addressId], authHeaders($session['accessToken']))->assertCreated();

        // Rate değişti
        DB::table('currency.exchange_rates')->where('id', $rateId)->update(['rate' => 0.06]);

        // Yeni cart + ikinci sipariş
        test()->postJson('/api/v1/sales/cart/items', ['productId' => $productId, 'quantity' => 1], authHeaders($session['accessToken']))->assertOk();
        $r2 = test()->postJson('/api/v1/sales/orders', ['displayCurrency' => 'USD', 'deliveryAddressId' => $addressId], authHeaders($session['accessToken']))->assertCreated();

        expect((float) $r1->json('data.exchangeRateSnapshot'))->toBe(0.04)
            ->and((float) $r2->json('data.exchangeRateSnapshot'))->toBe(0.06)
            ->and((float) $r1->json('data.displayTotal'))->toBe(4.00)   // 100 × 0.04
            ->and((float) $r2->json('data.displayTotal'))->toBe(6.00);  // 100 × 0.06
    });
});
