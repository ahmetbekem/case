<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Infrastructure\Identity\Models\UserModel;

function seedAdminAndProducts(int $count = 5): array
{
    $email = 'list-admin-' . uniqid() . '@catalog.test';
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => 'admin1234',
        'fullName' => 'List Admin',
    ])->assertCreated();
    UserModel::query()->where('email', $email)->update(['role' => UserRole::ADMIN->value]);

    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'admin1234',
    ])->assertOk();
    $token = $login->json('data.accessToken');

    for ($i = 1; $i <= $count; $i++) {
        test()->postJson(
            '/api/v1/catalog/products',
            [
                'sku' => "LIST-{$i}",
                'name' => $i % 2 === 0 ? "Widget Alpha {$i}" : "Gadget Beta {$i}",
                'price' => 10.0 * $i,
                'currency' => 'TRY',
                'stock' => $i,
            ],
            authHeaders($token),
        )->assertCreated();
    }

    return ['accessToken' => $token];
}

describe('GET /catalog/products — listing', function () {
    it('returns items + meta with default pagination', function () {
        seedAdminAndProducts(3);

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();

        expect($response->json('data'))->toBeArray()->toHaveCount(3)
            ->and($response->json('meta.page'))->toBe(1)
            ->and($response->json('meta.pageSize'))->toBe(20)
            ->and($response->json('meta.total'))->toBe(3)
            ->and($response->json('meta.totalPages'))->toBe(1);
    });

    it('honours pageSize parameter', function () {
        seedAdminAndProducts(5);

        $page1 = $this->getJson('/api/v1/catalog/products?page=1&pageSize=2')->assertOk();
        $page2 = $this->getJson('/api/v1/catalog/products?page=2&pageSize=2')->assertOk();
        $page3 = $this->getJson('/api/v1/catalog/products?page=3&pageSize=2')->assertOk();

        expect($page1->json('data'))->toHaveCount(2)
            ->and($page2->json('data'))->toHaveCount(2)
            ->and($page3->json('data'))->toHaveCount(1)
            ->and($page1->json('meta.total'))->toBe(5)
            ->and($page1->json('meta.totalPages'))->toBe(3);
    });

    it('search filters by name (ILIKE / trigram index)', function () {
        seedAdminAndProducts(4);  // creates 2 "Widget Alpha", 2 "Gadget Beta"

        $response = $this->getJson('/api/v1/catalog/products?search=Widget')->assertOk();

        $names = array_column($response->json('data'), 'name');
        foreach ($names as $name) {
            expect($name)->toContain('Widget');
        }
    });

    it('caps pageSize at 100 (handler-side guard)', function () {
        seedAdminAndProducts(1);

        $response = $this->getJson('/api/v1/catalog/products?pageSize=500')->assertOk();

        expect($response->json('meta.pageSize'))->toBe(100);
    });
});
