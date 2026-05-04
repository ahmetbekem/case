<?php

declare(strict_types=1);

describe('GET /api/v1/auth/me', function () {
    it('returns the authenticated user', function () {
        $session = registerAndLoginCustomer('me@test.local');

        $response = $this->getJson('/api/v1/auth/me', authHeaders($session['accessToken']))
            ->assertOk();

        expect($response->json('data.id'))->toBe($session['userId'])
            ->and($response->json('data.email'))->toBe('me@test.local')
            ->and($response->json('data.role'))->toBe('customer');

        // Sanity: no passwordHash leak
        expect($response->json('data'))->not->toHaveKey('passwordHash')
            ->and($response->json('data'))->not->toHaveKey('password_hash');
    });

    it('returns 401 MISSING_ACCESS_TOKEN with no Authorization header', function () {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'MISSING_ACCESS_TOKEN');
    });

    it('returns 401 INVALID_ACCESS_TOKEN for a malformed JWT', function () {
        $this->getJson('/api/v1/auth/me', authHeaders('not-a-real-jwt'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_ACCESS_TOKEN');
    });

    it('returns 401 INVALID_ACCESS_TOKEN for a JWT signed with a different secret', function () {
        // Forge an HS256 JWT with a wrong secret — must be rejected by HmacJwtIssuer::verify
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => config('app.name'),
            'aud' => config('jwt.audience', 'dehasoft-app'),
            'sub' => '11111111-2222-3333-4444-555555555555',
            'iat' => time(),
            'exp' => time() + 3600,
            'jti' => 'forged',
            'role' => 'admin',
        ])), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", 'wrong-secret-for-signature-test', true)), '+/', '-_'), '=');
        $forged = "{$header}.{$payload}.{$sig}";

        $this->getJson('/api/v1/auth/me', authHeaders($forged))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_ACCESS_TOKEN');
    });
});
