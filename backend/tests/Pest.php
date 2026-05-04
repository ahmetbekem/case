<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| Tests in `Feature/` and `Unit/Application/` extend Tests\TestCase to get
| the Laravel application container. Tests in `Unit/Domain/` are intentionally
| pure-PHP — they run without booting Laravel for maximum speed and to
| enforce Domain layer's framework independence (CODING_STANDARDS §19.4).
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit/Application');
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Register + login a customer in one shot. Returns the JSON of the login
 * response (data.user, data.accessToken, data.refreshToken, ...).
 *
 * @return array{userId: string, accessToken: string, refreshToken: string, email: string}
 */
function registerAndLoginCustomer(
    string $email = 'cust@test.local',
    string $password = 'secret123',
    string $fullName = 'Test Customer',
): array {
    test()->postJson('/api/v1/auth/register', [
        'email' => $email,
        'password' => $password,
        'fullName' => $fullName,
    ])->assertCreated();

    $login = test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk();

    return [
        'userId' => $login->json('data.user.id'),
        'accessToken' => $login->json('data.accessToken'),
        'refreshToken' => $login->json('data.refreshToken'),
        'email' => $email,
    ];
}

function authHeaders(string $accessToken): array
{
    return [
        'Authorization' => "Bearer {$accessToken}",
        'Accept' => 'application/json',
    ];
}
