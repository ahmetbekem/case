<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Infrastructure\Identity\Models\UserModel;
use Ramsey\Uuid\Uuid;

/**
 * ADR-017 — Login/Register sırasında MergeGuestCartHandler tetikleme +
 * 3 senaryo (no user cart → transferOwnership; both → CartMerger;
 * no guest cookie → cartMerged false).
 */

function seedProductForMerge(string $sku, float $price = 100.0, int $stock = 100): string
{
    $email = 'admin-' . uniqid() . '@merge.test';
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => 'admin1234',
        'fullName' => 'Merge Admin',
    ])->assertCreated();
    UserModel::query()->where('email', $email)->update(['role' => UserRole::ADMIN->value]);
    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'admin1234',
    ])->assertOk();

    $created = test()->postJson(
        '/api/v1/catalog/products',
        [
            'sku' => $sku,
            'name' => 'Merge Product ' . $sku,
            'price' => $price,
            'currency' => 'TRY',
            'stock' => $stock,
        ],
        ['Authorization' => 'Bearer ' . $login->json('data.accessToken'), 'Accept' => 'application/json'],
    )->assertCreated();
    return $created->json('data.id');
}

function createGuestCartWithItem(string $productId, int $quantity = 1): string
{
    $r = test()->postJson('/api/v1/sales/cart/items', [
        'productId' => $productId,
        'quantity' => $quantity,
    ])->assertOk();
    $sessionId = $r->json('data.guestSessionId');
    expect($sessionId)->not->toBeEmpty();
    return $sessionId;
}

describe('ADR-017 — login/register guest cart merge', function () {
    it('login without guest cookie returns cartMerged: false', function () {
        $email = 'no-merge-' . uniqid() . '@test.local';
        test()->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'pass1234',
            'fullName' => 'No Merge',
        ])->assertCreated();

        $response = test()->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'pass1234',
        ])->assertOk();

        expect($response->json('data.cartMerged'))->toBeFalse();
    });

    it('register without guest cookie returns cartMerged: false', function () {
        $email = 'reg-no-merge-' . uniqid() . '@test.local';
        $response = test()->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'pass1234',
            'fullName' => 'Reg No Merge',
        ])->assertCreated();

        expect($response->json('data.cartMerged'))->toBeFalse();
    });

    it('register with guest cookie transfers cart ownership', function () {
        $productId = seedProductForMerge('REG-XFER', 50.0, 10);
        $sessionId = createGuestCartWithItem($productId, 3);

        // Yeni kullanıcı — register sonrası user cart yok, transfer beklenir
        $email = 'reg-merge-' . uniqid() . '@test.local';
        $response = test()->call(
            method: 'POST',
            uri: '/api/v1/auth/register',
            parameters: [],
            cookies: ['guest_cart_token' => $sessionId],
            files: [],
            server: test()->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]),
            content: json_encode([
                'email' => $email,
                'password' => 'pass1234',
                'fullName' => 'Reg Merge',
            ]),
        );

        $response->assertCreated();
        expect($response->json('data.cartMerged'))->toBeTrue();

        $userId = $response->json('data.user.id');
        $userCart = DB::table('sales.carts')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        expect($userCart)->not->toBeNull();
        expect($userCart->guest_session_id)->toBeNull();
        expect((int) DB::table('sales.cart_items')
            ->where('cart_id', $userCart->id)
            ->sum('quantity'))->toBe(3);
    });

    it('login with guest cookie when user has no cart transfers ownership', function () {
        // Önce kullanıcı yarat — user cart oluşturmadan logout
        $email = 'xfer-' . uniqid() . '@test.local';
        $session = registerAndLoginCustomer($email, 'pass1234', 'Xfer User');

        $productId = seedProductForMerge('LOGIN-XFER', 50.0, 10);
        $sessionId = createGuestCartWithItem($productId, 2);

        $response = test()->call(
            method: 'POST',
            uri: '/api/v1/auth/login',
            parameters: [],
            cookies: ['guest_cart_token' => $sessionId],
            files: [],
            server: test()->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]),
            content: json_encode(['email' => $email, 'password' => 'pass1234']),
        )->assertOk();

        expect($response->json('data.cartMerged'))->toBeTrue();

        $userCart = DB::table('sales.carts')
            ->where('user_id', $session['userId'])
            ->where('status', 'active')
            ->first();
        expect($userCart)->not->toBeNull();
        expect($userCart->guest_session_id)->toBeNull();
        expect((int) DB::table('sales.cart_items')
            ->where('cart_id', $userCart->id)
            ->sum('quantity'))->toBe(2);
    });

    it('login merges quantities when both carts contain the same product', function () {
        $email = 'qtymerge-' . uniqid() . '@test.local';
        $session = registerAndLoginCustomer($email, 'pass1234', 'Qty Merge');

        $productId = seedProductForMerge('QTY-MERGE', 50.0, 100);

        // User cart'a 3 adet ekle
        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 3],
            authHeaders($session['accessToken']),
        )->assertOk();

        // Guest cart aynı ürünü 2 adet
        $guestSessionId = createGuestCartWithItem($productId, 2);

        // Login + merge
        $login = test()->call(
            method: 'POST',
            uri: '/api/v1/auth/login',
            parameters: [],
            cookies: ['guest_cart_token' => $guestSessionId],
            files: [],
            server: test()->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]),
            content: json_encode(['email' => $email, 'password' => 'pass1234']),
        )->assertOk();

        expect($login->json('data.cartMerged'))->toBeTrue();

        $userCart = DB::table('sales.carts')
            ->where('user_id', $session['userId'])
            ->where('status', 'active')
            ->first();
        expect((int) DB::table('sales.cart_items')
            ->where('cart_id', $userCart->id)
            ->where('product_id', $productId)
            ->sum('quantity'))->toBe(5);

        // Guest cart abandoned olmuş olmalı (uniq_carts_guest_active boş)
        $abandonedGuest = DB::table('sales.carts')
            ->where('guest_session_id', $guestSessionId)
            ->first();
        expect($abandonedGuest->status)->toBe('abandoned');
    });
});
