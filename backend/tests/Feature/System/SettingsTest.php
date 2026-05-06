<?php

declare(strict_types=1);

use Domain\Identity\Enums\UserRole;
use Domain\Identity\ValueObjects\Email;
use Domain\Identity\ValueObjects\HashedPassword;
use Domain\Shared\ValueObjects\UserId;
use Infrastructure\Identity\Models\UserModel;
use Infrastructure\System\Models\SettingModel;
use Ramsey\Uuid\Uuid;

function seedSetting(string $key, string $value, bool $isPublic = false, string $type = 'string'): void
{
    SettingModel::query()->insert([
        'id' => Uuid::uuid4()->toString(),
        'key' => $key,
        'value' => $value,
        'type' => $type,
        'category' => 'general',
        'description' => null,
        'is_public' => $isPublic,
        'created_at' => now(),
    ]);
}

function seedUserForSettings(string $role = 'admin'): array
{
    $id = Uuid::uuid4()->toString();
    $email = $role . '+' . substr($id, 0, 6) . '@dehasoft.test';
    UserModel::query()->insert([
        'id' => $id,
        'email' => $email,
        'password_hash' => bcrypt('Password123!'),
        'full_name' => ucfirst($role) . ' Test',
        'role' => $role,
        'is_active' => true,
        'created_at' => now(),
    ]);
    return ['id' => $id, 'email' => $email];
}

function loginAndGetToken(array $user): string
{
    $resp = test()->postJson('/api/v1/auth/login', [
        'email' => $user['email'],
        'password' => 'Password123!',
    ])->assertOk();

    return (string) $resp->json('data.accessToken');
}

describe('GET /system/settings/public', function () {
    it('returns only is_public=true settings without auth', function () {
        seedSetting('base_currency', 'TRY', isPublic: true);
        seedSetting('internal_flag', 'secret', isPublic: false);

        $response = test()->getJson('/api/v1/system/settings/public')->assertOk();

        $keys = array_column($response->json('data'), 'key');
        expect($keys)->toContain('base_currency')
            ->not->toContain('internal_flag');
    });
});

describe('GET /admin/system/settings', function () {
    it('rejects unauthenticated callers', function () {
        test()->getJson('/api/v1/admin/system/settings')->assertUnauthorized();
    });

    it('rejects non-admin callers', function () {
        $customer = seedUserForSettings(role: 'customer');
        $token = loginAndGetToken($customer);

        test()->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/system/settings')
            ->assertForbidden();
    });

    it('returns all settings to admin', function () {
        seedSetting('base_currency', 'TRY', isPublic: true);
        seedSetting('internal_flag', 'secret', isPublic: false);
        $admin = seedUserForSettings(role: 'admin');
        $token = loginAndGetToken($admin);

        $response = test()->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/system/settings')
            ->assertOk();

        $keys = array_column($response->json('data'), 'key');
        expect($keys)->toContain('base_currency', 'internal_flag');
    });
});

describe('PATCH /admin/system/settings/{key}', function () {
    it('updates an existing setting + audit', function () {
        seedSetting('base_currency', 'TRY', isPublic: true);
        $admin = seedUserForSettings(role: 'admin');
        $token = loginAndGetToken($admin);

        $response = test()->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => 'USD'])
            ->assertOk();

        expect($response->json('data.value'))->toBe('USD')
            ->and($response->json('message'))->toBe('Setting updated');

        $row = SettingModel::query()->where('key', 'base_currency')->first();
        expect($row->value)->toBe('USD');
    });

    it('returns 404 for unknown key', function () {
        $admin = seedUserForSettings(role: 'admin');
        $token = loginAndGetToken($admin);

        test()->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/system/settings/never_seeded', ['value' => 'x'])
            ->assertNotFound()
            ->assertJson(['error' => ['code' => 'SETTING_NOT_FOUND']]);
    });

    it('rejects empty value', function () {
        seedSetting('base_currency', 'TRY', isPublic: true);
        $admin = seedUserForSettings(role: 'admin');
        $token = loginAndGetToken($admin);

        test()->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/system/settings/base_currency', ['value' => ''])
            ->assertStatus(422);
    });
});
