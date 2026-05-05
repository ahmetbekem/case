<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Infrastructure\Identity\Models\UserModel;
use Ramsey\Uuid\Uuid;

function loginAsAdminForCatalogSeed(): array
{
    $email = 'admin-' . uniqid() . '@sales.test';
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => 'admin1234',
        'fullName' => 'Sales Catalog Admin',
    ])->assertCreated();
    UserModel::query()->where('email', $email)->update(['role' => UserRole::ADMIN->value]);

    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'admin1234',
    ])->assertOk();

    return ['accessToken' => $login->json('data.accessToken')];
}

function seedProduct(string $sku = 'SALES-001', float $price = 100.0, int $stock = 50): string
{
    $admin = loginAsAdminForCatalogSeed();
    $created = test()->postJson(
        '/api/v1/catalog/products',
        [
            'sku' => $sku,
            'name' => 'Sales Product',
            'price' => $price,
            'currency' => 'TRY',
            'stock' => $stock,
        ],
        authHeaders($admin['accessToken']),
    )->assertCreated();
    return $created->json('data.id');
}

describe('Cart endpoints', function () {
    it('GET /cart on a fresh user returns an empty cart', function () {
        $session = registerAndLoginCustomer('cart-fresh@user.test');

        $response = $this->getJson('/api/v1/sales/cart', authHeaders($session['accessToken']))
            ->assertOk();

        expect($response->json('data.items'))->toBeArray()->toBeEmpty()
            ->and($response->json('data.itemCount'))->toBe(0)
            ->and($response->json('data.totalQuantity'))->toBe(0);
    });

    it('POST /cart/items adds an item and bumps totals', function () {
        $session = registerAndLoginCustomer('cart-add@user.test');
        $productId = seedProduct('CART-ADD-1', 50.0, 10);

        $response = $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 2],
            authHeaders($session['accessToken']),
        )->assertOk();

        expect($response->json('data.itemCount'))->toBe(1)
            ->and($response->json('data.totalQuantity'))->toBe(2)
            ->and((float) $response->json('data.subtotal'))->toBe(100.0);
    });

    it('POST /cart/items twice on the same product increments quantity', function () {
        $session = registerAndLoginCustomer('cart-bump@user.test');
        $productId = seedProduct('CART-BUMP-1', 50.0, 10);

        $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 2],
            authHeaders($session['accessToken']),
        )->assertOk();

        $response = $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 3],
            authHeaders($session['accessToken']),
        )->assertOk();

        expect($response->json('data.itemCount'))->toBe(1)
            ->and($response->json('data.totalQuantity'))->toBe(5);
    });

    it('PATCH /cart/items/{id} updates quantity', function () {
        $session = registerAndLoginCustomer('cart-patch@user.test');
        $productId = seedProduct('CART-PATCH-1', 50.0, 10);

        $created = $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();
        $itemId = $created->json('data.items.0.id');

        $response = $this->patchJson(
            "/api/v1/sales/cart/items/{$itemId}",
            ['quantity' => 7],
            authHeaders($session['accessToken']),
        )->assertOk();

        expect($response->json('data.totalQuantity'))->toBe(7);
    });

    it('DELETE /cart/items/{id} removes a line', function () {
        $session = registerAndLoginCustomer('cart-del@user.test');
        $productId = seedProduct('CART-DEL-1', 50.0, 10);

        $created = $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => $productId, 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertOk();
        $itemId = $created->json('data.items.0.id');

        $response = $this->deleteJson(
            "/api/v1/sales/cart/items/{$itemId}",
            [],
            authHeaders($session['accessToken']),
        )->assertOk();

        expect($response->json('data.itemCount'))->toBe(0);
    });

    it('DELETE /cart clears every item', function () {
        $session = registerAndLoginCustomer('cart-clear@user.test');
        $p1 = seedProduct('CART-CLR-1', 50.0, 10);
        $p2 = seedProduct('CART-CLR-2', 30.0, 10);

        $this->postJson('/api/v1/sales/cart/items', ['productId' => $p1, 'quantity' => 1], authHeaders($session['accessToken']))->assertOk();
        $this->postJson('/api/v1/sales/cart/items', ['productId' => $p2, 'quantity' => 1], authHeaders($session['accessToken']))->assertOk();

        $response = $this->deleteJson('/api/v1/sales/cart', [], authHeaders($session['accessToken']))
            ->assertOk();

        expect($response->json('data.itemCount'))->toBe(0);
    });

    it('rejects adding unknown product with 404 PRODUCT_NOT_FOUND', function () {
        $session = registerAndLoginCustomer('cart-bad@user.test');

        $this->postJson(
            '/api/v1/sales/cart/items',
            ['productId' => Uuid::uuid4()->toString(), 'quantity' => 1],
            authHeaders($session['accessToken']),
        )->assertStatus(404)
          ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    });

    it('anonymous → 401 on every cart endpoint', function () {
        $this->getJson('/api/v1/sales/cart')->assertStatus(401);
    });
});
