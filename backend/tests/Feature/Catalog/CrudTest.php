<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Infrastructure\Identity\Models\UserModel;

/**
 * Spin up an admin user, log them in, return their access token.
 * The Identity register endpoint always issues a 'customer' role, so we
 * promote the row directly in the DB before login. Until ADR-008's admin
 * provisioning command lands this is the testing-only escape hatch.
 */
function loginAsAdmin(): array
{
    $email = 'admin-' . uniqid() . '@catalog.test';
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => 'admin1234',
        'fullName' => 'Catalog Admin',
    ])->assertCreated();

    UserModel::query()->where('email', $email)->update(['role' => UserRole::ADMIN->value]);

    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'admin1234',
    ])->assertOk();

    return [
        'userId' => $login->json('data.user.id'),
        'accessToken' => $login->json('data.accessToken'),
    ];
}

describe('Catalog CRUD — admin happy paths', function () {
    it('admin can create a product', function () {
        $admin = loginAsAdmin();

        $response = $this->postJson(
            '/api/v1/catalog/products',
            [
                'sku' => 'NEW-CRUD-001',
                'name' => 'Brand New',
                'description' => 'Description here',
                'price' => 199.99,
                'currency' => 'TRY',
                'stock' => 50,
            ],
            authHeaders($admin['accessToken']),
        )->assertCreated();

        expect($response->json('data.sku'))->toBe('NEW-CRUD-001')
            ->and($response->json('data.name'))->toBe('Brand New')
            ->and($response->json('data.stock'))->toBe(50)
            ->and($response->json('data.price'))->toBe(199.99)
            ->and($response->json('data.currency'))->toBe('TRY')
            ->and($response->json('data.isActive'))->toBeTrue();
    });

    it('admin can update a product partially', function () {
        $admin = loginAsAdmin();

        $created = $this->postJson(
            '/api/v1/catalog/products',
            [
                'sku' => 'UPD-CRUD-001',
                'name' => 'Old Name',
                'price' => 100.00,
                'currency' => 'TRY',
                'stock' => 20,
            ],
            authHeaders($admin['accessToken']),
        )->assertCreated();

        $id = $created->json('data.id');

        // Only change name + price; description/currency/isActive untouched.
        // Use a non-integer float so JSON decoding doesn't coerce to int
        // (json_encode(250.00) → "250" → decoded as int).
        $this->patchJson(
            "/api/v1/catalog/products/{$id}",
            ['name' => 'New Name', 'price' => 250.50],
            authHeaders($admin['accessToken']),
        )->assertOk()
          ->assertJsonPath('data.name', 'New Name')
          ->assertJsonPath('data.price', 250.50)
          ->assertJsonPath('data.stock', 20);
    });

    it('admin can soft-delete a product', function () {
        $admin = loginAsAdmin();

        $id = $this->postJson(
            '/api/v1/catalog/products',
            [
                'sku' => 'DEL-CRUD-001',
                'name' => 'To Be Deleted',
                'price' => 50.00,
                'currency' => 'TRY',
                'stock' => 1,
            ],
            authHeaders($admin['accessToken']),
        )->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/catalog/products/{$id}", [], authHeaders($admin['accessToken']))
            ->assertNoContent();

        // Soft-deleted product is no longer reachable
        $this->getJson("/api/v1/catalog/products/{$id}")->assertStatus(404);
    });

    it('admin can adjust stock up and down', function () {
        $admin = loginAsAdmin();
        $id = $this->postJson(
            '/api/v1/catalog/products',
            ['sku' => 'STK-001', 'name' => 'Stock Test', 'price' => 10, 'currency' => 'TRY', 'stock' => 10],
            authHeaders($admin['accessToken']),
        )->assertCreated()->json('data.id');

        $this->postJson("/api/v1/catalog/products/{$id}/stock", ['delta' => 5], authHeaders($admin['accessToken']))
            ->assertOk()
            ->assertJsonPath('data.stock', 15);

        $this->postJson("/api/v1/catalog/products/{$id}/stock", ['delta' => -8], authHeaders($admin['accessToken']))
            ->assertOk()
            ->assertJsonPath('data.stock', 7);
    });

    it('rejects stock adjustment that would underflow with 409 INSUFFICIENT_STOCK', function () {
        $admin = loginAsAdmin();
        $id = $this->postJson(
            '/api/v1/catalog/products',
            ['sku' => 'STK-002', 'name' => 'Tiny', 'price' => 10, 'currency' => 'TRY', 'stock' => 3],
            authHeaders($admin['accessToken']),
        )->assertCreated()->json('data.id');

        $this->postJson("/api/v1/catalog/products/{$id}/stock", ['delta' => -10], authHeaders($admin['accessToken']))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    });
});

describe('Catalog CRUD — authorization', function () {
    it('customer cannot create products (403 ROLE_DENIED)', function () {
        $customer = registerAndLoginCustomer('customer@catalog.test');

        $this->postJson(
            '/api/v1/catalog/products',
            ['sku' => 'CUS-001', 'name' => 'Customer Try', 'price' => 100, 'currency' => 'TRY', 'stock' => 10],
            authHeaders($customer['accessToken']),
        )->assertStatus(403)
          ->assertJsonPath('error.code', 'ROLE_DENIED');
    });

    it('anonymous cannot create products (401)', function () {
        $this->postJson('/api/v1/catalog/products', [
            'sku' => 'ANON-001',
            'name' => 'Anon',
            'price' => 100,
            'currency' => 'TRY',
            'stock' => 10,
        ])->assertStatus(401);
    });

    it('anonymous CAN list products (public read)', function () {
        $admin = loginAsAdmin();
        $this->postJson(
            '/api/v1/catalog/products',
            ['sku' => 'PUB-001', 'name' => 'Public', 'price' => 10, 'currency' => 'TRY', 'stock' => 5],
            authHeaders($admin['accessToken']),
        )->assertCreated();

        $this->getJson('/api/v1/catalog/products')->assertOk();
    });
});

describe('Catalog CRUD — validation + uniqueness', function () {
    it('rejects invalid SKU format with 422', function () {
        $admin = loginAsAdmin();

        // Spaces / special chars survive prepareForValidation()'s strtoupper
        // so they actually exercise the regex /^[A-Z0-9\-]+$/.
        $this->postJson(
            '/api/v1/catalog/products',
            ['sku' => 'BAD SKU', 'name' => 'Valid Name', 'price' => 1, 'currency' => 'TRY', 'stock' => 1],
            authHeaders($admin['accessToken']),
        )->assertStatus(422)
          ->assertJsonValidationErrors(['sku']);
    });

    it('rejects duplicate SKU with 409 DUPLICATE_SKU', function () {
        $admin = loginAsAdmin();
        $payload = ['sku' => 'DUP-001', 'name' => 'Dup Name', 'price' => 1, 'currency' => 'TRY', 'stock' => 1];

        $this->postJson('/api/v1/catalog/products', $payload, authHeaders($admin['accessToken']))->assertCreated();
        $this->postJson('/api/v1/catalog/products', $payload, authHeaders($admin['accessToken']))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_SKU');
    });

    it('GET unknown product → 404 PRODUCT_NOT_FOUND', function () {
        $this->getJson('/api/v1/catalog/products/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    });
});
