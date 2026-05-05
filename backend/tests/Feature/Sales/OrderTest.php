<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Currency\Models\ExchangeRateModel;
use Infrastructure\Identity\Models\UserModel;
use Ramsey\Uuid\Uuid;

function placeOrderHelpers_loginAsAdmin(): array
{
    $email = 'admin-' . uniqid() . '@order.test';
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => 'admin1234',
        'fullName' => 'Order Admin',
    ])->assertCreated();
    UserModel::query()->where('email', $email)->update(['role' => UserRole::ADMIN->value]);

    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'admin1234',
    ])->assertOk();

    return [
        'accessToken' => $login->json('data.accessToken'),
        'userId' => $login->json('data.user.id'),
    ];
}

function seedProductForOrders(string $sku, float $price = 100.0, int $stock = 10): string
{
    $admin = placeOrderHelpers_loginAsAdmin();
    return test()->postJson(
        '/api/v1/catalog/products',
        ['sku' => $sku, 'name' => "P-{$sku}", 'price' => $price, 'currency' => 'TRY', 'stock' => $stock],
        authHeaders($admin['accessToken']),
    )->assertCreated()->json('data.id');
}

function seedExchangeRate(string $base, string $target, float $rate): void
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

function buildCartAndPlaceOrder(array $session, string $productId, int $qty = 2): array
{
    test()->postJson(
        '/api/v1/sales/cart/items',
        ['productId' => $productId, 'quantity' => $qty],
        authHeaders($session['accessToken']),
    )->assertOk();

    $response = test()->postJson(
        '/api/v1/sales/orders',
        ['displayCurrency' => 'TRY'],
        authHeaders($session['accessToken']),
    )->assertCreated();

    return [
        'orderId' => $response->json('data.id'),
        'orderNumber' => $response->json('data.orderNumber'),
        'data' => $response->json('data'),
    ];
}

describe('POST /sales/orders — happy path', function () {
    it('creates an order from cart, decreases stock, marks cart converted', function () {
        $session = registerAndLoginCustomer('order-happy@user.test');
        $productId = seedProductForOrders('ORD-HAPPY-1', 50.0, 10);

        $result = buildCartAndPlaceOrder($session, $productId, 3);
        $data = $result['data'];

        expect($data['status'])->toBe('pending')
            ->and($data['itemCount'])->toBe(1)
            ->and((float) $data['subtotal'])->toBe(150.0)
            ->and((float) $data['total'])->toBe(150.0)
            ->and($data['displayCurrency'])->toBe('TRY')
            ->and($data['orderNumber'])->toMatch('/^ORD-\d{4}-\d{6}$/');

        // Stock decreased
        $stockNow = (int) ProductModel::query()->where('id', $productId)->value('stock');
        expect($stockNow)->toBe(7);

        // Cart can be re-used (new active cart will be created)
        $newCart = $this->getJson('/api/v1/sales/cart', authHeaders($session['accessToken']))->assertOk();
        expect($newCart->json('data.itemCount'))->toBe(0);
    });

    it('converts subtotal to displayCurrency using latest rate', function () {
        $session = registerAndLoginCustomer('order-fx@user.test');
        $productId = seedProductForOrders('ORD-FX-1', 100.0, 10);
        seedExchangeRate('TRY', 'USD', 0.034);

        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 2],
            authHeaders($session['accessToken']),
        )->assertOk();

        $response = $this->postJson(
            '/api/v1/sales/orders',
            ['displayCurrency' => 'USD'],
            authHeaders($session['accessToken']),
        )->assertCreated();

        expect((float) $response->json('data.subtotal'))->toBe(200.0)
            ->and($response->json('data.displayCurrency'))->toBe('USD')
            ->and((float) $response->json('data.displayTotal'))->toBe(6.80) // 200 * 0.034
            ->and((float) $response->json('data.exchangeRateSnapshot'))->toBe(0.034);
    });
});

describe('POST /sales/orders — failures', function () {
    it('rejects empty cart with 409 EMPTY_CART', function () {
        $session = registerAndLoginCustomer('order-empty@user.test');

        $this->postJson(
            '/api/v1/sales/orders',
            ['displayCurrency' => 'TRY'],
            authHeaders($session['accessToken']),
        )->assertStatus(409)
          ->assertJsonPath('error.code', 'EMPTY_CART');
    });

    it('rejects insufficient stock with 409 INSUFFICIENT_STOCK', function () {
        $session = registerAndLoginCustomer('order-stock@user.test');
        $productId = seedProductForOrders('ORD-LOW-1', 50.0, 2);

        test()->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 5],
            authHeaders($session['accessToken']),
        )->assertOk();

        $this->postJson(
            '/api/v1/sales/orders',
            ['displayCurrency' => 'TRY'],
            authHeaders($session['accessToken']),
        )->assertStatus(409)
          ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    });
});

describe('GET /sales/orders/{id} — IDOR protection', function () {
    it('owner can read their own order', function () {
        $session = registerAndLoginCustomer('order-own@user.test');
        $productId = seedProductForOrders('ORD-OWN-1', 50.0, 10);
        $orderId = buildCartAndPlaceOrder($session, $productId)['orderId'];

        $this->getJson("/api/v1/sales/orders/{$orderId}", authHeaders($session['accessToken']))
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    });

    it('admin can read anyone\'s order', function () {
        $userA = registerAndLoginCustomer('order-victim@user.test');
        $productId = seedProductForOrders('ORD-AV-1', 50.0, 10);
        $orderId = buildCartAndPlaceOrder($userA, $productId)['orderId'];
        $admin = placeOrderHelpers_loginAsAdmin();

        $this->getJson("/api/v1/sales/orders/{$orderId}", authHeaders($admin['accessToken']))
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    });

    it('IDOR: User A cannot see User B\'s order — 404 ORDER_NOT_FOUND (not 403)', function () {
        $userB = registerAndLoginCustomer('order-target@user.test');
        $productId = seedProductForOrders('ORD-IDOR-1', 50.0, 10);
        $bsOrderId = buildCartAndPlaceOrder($userB, $productId)['orderId'];

        $userA = registerAndLoginCustomer('order-attacker@user.test');

        $response = $this->getJson("/api/v1/sales/orders/{$bsOrderId}", authHeaders($userA['accessToken']))
            ->assertStatus(404);

        // Critical: 404 ORDER_NOT_FOUND, NOT 403 — anti-enumeration
        expect($response->json('error.code'))->toBe('ORDER_NOT_FOUND');
    });

    it('non-existent order id → 404 ORDER_NOT_FOUND (same response shape as IDOR)', function () {
        $session = registerAndLoginCustomer('order-404@user.test');

        $this->getJson('/api/v1/sales/orders/' . Uuid::uuid4()->toString(), authHeaders($session['accessToken']))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    });
});

describe('POST /sales/orders/{id}/cancel', function () {
    it('owner cancels and stock is restored', function () {
        $session = registerAndLoginCustomer('order-cancel@user.test');
        $productId = seedProductForOrders('ORD-CNL-1', 50.0, 10);
        $orderId = buildCartAndPlaceOrder($session, $productId, 4)['orderId'];

        // Stock is now 6 (10 - 4)
        expect((int) ProductModel::query()->where('id', $productId)->value('stock'))->toBe(6);

        $this->postJson(
            "/api/v1/sales/orders/{$orderId}/cancel",
            ['reason' => 'Changed my mind'],
            authHeaders($session['accessToken']),
        )->assertOk()
          ->assertJsonPath('data.status', 'cancelled')
          ->assertJsonPath('data.cancelledReason', 'Changed my mind');

        // Restocked
        expect((int) ProductModel::query()->where('id', $productId)->value('stock'))->toBe(10);
    });

    it('IDOR: User A cannot cancel User B\'s order — 404 ORDER_NOT_FOUND', function () {
        $userB = registerAndLoginCustomer('order-cnl-victim@user.test');
        $productId = seedProductForOrders('ORD-CNL-IDOR-1', 50.0, 10);
        $bsOrderId = buildCartAndPlaceOrder($userB, $productId)['orderId'];

        $userA = registerAndLoginCustomer('order-cnl-attacker@user.test');

        $this->postJson(
            "/api/v1/sales/orders/{$bsOrderId}/cancel",
            ['reason' => 'I should not be allowed'],
            authHeaders($userA['accessToken']),
        )->assertStatus(404)
          ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    });

    it('cancelling an already-cancelled order → 409 ORDER_CANNOT_BE_CANCELLED', function () {
        $session = registerAndLoginCustomer('order-double-cnl@user.test');
        $productId = seedProductForOrders('ORD-DBL-CNL-1', 50.0, 10);
        $orderId = buildCartAndPlaceOrder($session, $productId)['orderId'];

        $this->postJson(
            "/api/v1/sales/orders/{$orderId}/cancel",
            ['reason' => 'first'],
            authHeaders($session['accessToken']),
        )->assertOk();

        $this->postJson(
            "/api/v1/sales/orders/{$orderId}/cancel",
            ['reason' => 'second'],
            authHeaders($session['accessToken']),
        )->assertStatus(409)
          ->assertJsonPath('error.code', 'ORDER_CANNOT_BE_CANCELLED');
    });
});

describe('GET /sales/orders — listing', function () {
    it('lists only the calling user\'s orders by default', function () {
        $userA = registerAndLoginCustomer('list-a@user.test');
        $userB = registerAndLoginCustomer('list-b@user.test');
        $productId = seedProductForOrders('LIST-1', 50.0, 10);

        buildCartAndPlaceOrder($userA, $productId);
        buildCartAndPlaceOrder($userB, $productId);

        $response = $this->getJson('/api/v1/sales/orders', authHeaders($userA['accessToken']))->assertOk();

        // Each order belongs to userA
        foreach ($response->json('data') as $order) {
            expect($order['userId'])->toBe($userA['userId']);
        }
        expect($response->json('meta.total'))->toBe(1);
    });

    it('admin with adminScope=1 sees every order', function () {
        $userA = registerAndLoginCustomer('admin-scope-a@user.test');
        $userB = registerAndLoginCustomer('admin-scope-b@user.test');
        $productId = seedProductForOrders('ADMIN-SCOPE-1', 50.0, 10);
        buildCartAndPlaceOrder($userA, $productId);
        buildCartAndPlaceOrder($userB, $productId);

        $admin = placeOrderHelpers_loginAsAdmin();

        $response = $this->getJson('/api/v1/sales/orders?adminScope=1', authHeaders($admin['accessToken']))
            ->assertOk();

        expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
    });
});
