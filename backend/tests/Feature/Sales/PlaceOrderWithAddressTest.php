<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * ADR-018 — Sipariş + adres snapshot entegrasyonu.
 */

function placeOrderWithBody(array $session, array $body): \Illuminate\Testing\TestResponse
{
    return test()->postJson(
        '/api/v1/sales/orders',
        $body,
        authHeaders($session['accessToken']),
    );
}

describe('PlaceOrder address requirements', function () {
    it('rejects missing deliveryAddressId with 422', function () {
        $session = registerAndLoginCustomer('order-no-addr@test.local');
        $productId = seedProductForOrders('ORD-NOADDR-1', 50.0, 5);
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();

        placeOrderWithBody($session, ['displayCurrency' => 'TRY'])
            ->assertStatus(422);
    });

    it('rejects another users address with 404 (IDOR)', function () {
        $userA = registerAndLoginCustomer('order-idor-a@test.local');
        $userB = registerAndLoginCustomer('order-idor-b@test.local');
        $bAddress = ensureAddressFor($userB);

        $productId = seedProductForOrders('ORD-IDOR-1', 50.0, 5);
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($userA['accessToken']),
        )->assertOk();

        placeOrderWithBody($userA, [
            'displayCurrency' => 'TRY',
            'deliveryAddressId' => $bAddress,
        ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ADDRESS_NOT_FOUND');

        // IDOR rollback: stok düşmemiş olmalı
        $stock = (int) DB::table('catalog.products')->where('id', $productId)->value('stock');
        expect($stock)->toBe(5);
    });

    it('snapshots address into orders.delivery_address JSONB', function () {
        $session = registerAndLoginCustomer('order-snapshot@test.local');
        $addressId = ensureAddressFor($session);
        $productId = seedProductForOrders('ORD-SNAP-1', 50.0, 5);
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();

        $response = placeOrderWithBody($session, [
            'displayCurrency' => 'TRY',
            'deliveryAddressId' => $addressId,
        ])->assertCreated();

        $orderId = $response->json('data.id');
        $row = DB::table('sales.orders')->where('id', $orderId)->first();
        $snapshot = is_string($row->delivery_address)
            ? json_decode($row->delivery_address, true)
            : (array) $row->delivery_address;

        expect($snapshot['city'])->toBe('İstanbul')
            ->and($snapshot['district'])->toBe('Kadıköy')
            ->and($snapshot)->toHaveKey('snapshotAt');
        expect($row->delivery_address_id)->toBe($addressId);
        // billingAddressId null verilmedi → delivery ile aynı
        expect($row->billing_address_id)->toBe($addressId);
    });

    it('uses different billing address when provided', function () {
        $session = registerAndLoginCustomer('order-different-billing@test.local');
        $delivery = ensureAddressFor($session);
        $billing = test()->postJson(
            '/api/v1/identity/addresses',
            [
                'title' => 'Fatura',
                'fullName' => 'Test User',
                'phone' => '+905551234567',
                'city' => 'Ankara',
                'district' => 'Çankaya',
                'addressLine' => 'Atatürk Bulvarı No: 123',
            ],
            authHeaders($session['accessToken']),
        )->assertCreated()->json('data.id');

        $productId = seedProductForOrders('ORD-DIFF-BILL', 50.0, 5);
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();

        $response = placeOrderWithBody($session, [
            'displayCurrency' => 'TRY',
            'deliveryAddressId' => $delivery,
            'billingAddressId' => $billing,
        ])->assertCreated();

        $billingSnapshot = $response->json('data.billingAddress');
        expect($billingSnapshot['city'])->toBe('Ankara')
            ->and($billingSnapshot['district'])->toBe('Çankaya');
    });

    it('order address survives source address deletion (snapshot pattern)', function () {
        $session = registerAndLoginCustomer('order-survive@test.local');
        $addressId = ensureAddressFor($session);
        $productId = seedProductForOrders('ORD-SURVIVE', 50.0, 5);
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();

        $orderId = placeOrderWithBody($session, [
            'displayCurrency' => 'TRY',
            'deliveryAddressId' => $addressId,
        ])->assertCreated()->json('data.id');

        // Adresi sil
        test()->deleteJson(
            '/api/v1/identity/addresses/' . $addressId,
            [],
            authHeaders($session['accessToken']),
        )->assertNoContent();

        // Sipariş hala adres bilgisini snapshot'tan döner
        $orderResponse = test()->getJson(
            '/api/v1/sales/orders/' . $orderId,
            authHeaders($session['accessToken']),
        )->assertOk();

        expect($orderResponse->json('data.deliveryAddress.city'))->toBe('İstanbul');
        // Soft delete olduğundan FK ON DELETE SET NULL TETIKLENMEZ —
        // ID hala referans olarak korunur; snapshot her hâlükârda
        // kalır. Hard delete (cleanup job veya admin purge) durumunda
        // FK null'a düşer; o senaryo ayrı testte.
        $row = DB::table('sales.orders')->where('id', $orderId)->first();
        expect($row->delivery_address_id)->toBe($addressId);
        $deletedAddress = DB::table('identity.addresses')->where('id', $addressId)->first();
        expect($deletedAddress->deleted_at)->not->toBeNull();
    });
});
