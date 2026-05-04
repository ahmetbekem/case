<?php

declare(strict_types=1);

describe('POST /api/v1/auth/register', function () {
    it('creates a user, issues tokens, returns 201', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@user.local',
            'password' => 'secret123',
            'fullName' => 'New User',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'new@user.local')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email', 'fullName', 'role', 'isActive', 'createdAt'],
                    'accessToken',
                    'accessTokenExpiresIn',
                    'refreshToken',
                    'tokenType',
                ],
            ]);

        // Refresh token plaintext: 64 hex chars
        expect($response->json('data.refreshToken'))->toMatch('/^[a-f0-9]{64}$/');
    });

    it('rejects duplicate email with 409 EMAIL_ALREADY_EXISTS', function () {
        registerAndLoginCustomer('dup@user.local');

        $this->postJson('/api/v1/auth/register', [
            'email' => 'dup@user.local',
            'password' => 'secret123',
            'fullName' => 'Dup',
        ])->assertStatus(409)
          ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');
    });

    it('lowercases email before persisting (Email VO + DB CHECK constraint)', function () {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'MixedCase@User.LOCAL',
            'password' => 'secret123',
            'fullName' => 'Mixed',
        ])->assertCreated()
          ->assertJsonPath('data.user.email', 'mixedcase@user.local');
    });

    it('rejects malformed email with 422', function () {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'secret123',
            'fullName' => 'X',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    });

    it('rejects short password with 422', function () {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'short@user.local',
            'password' => 'abc',
            'fullName' => 'X',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    });
});

describe('POST /api/v1/auth/login', function () {
    it('returns tokens + user on correct credentials', function () {
        registerAndLoginCustomer('happy@login.local', 'secret123');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'happy@login.local',
            'password' => 'secret123',
        ])->assertOk();

        expect($response->json('data.accessToken'))->toBeString()
            ->and($response->json('data.refreshToken'))->toMatch('/^[a-f0-9]{64}$/')
            ->and($response->json('data.user.email'))->toBe('happy@login.local');
    });

    it('returns 401 INVALID_CREDENTIALS on wrong password (no enumeration)', function () {
        registerAndLoginCustomer('victim@login.local');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@login.local',
            'password' => 'WRONG_PASSWORD',
        ])->assertStatus(401)
          ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    });

    it('returns the same 401 INVALID_CREDENTIALS for unknown email (no enumeration)', function () {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'never-existed@nowhere.local',
            'password' => 'secret123',
        ])->assertStatus(401)
          ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    });

    it('returns 401 (NOT 422) for too-short password — protects against length oracle', function () {
        registerAndLoginCustomer('short@oracle.local');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'short@oracle.local',
            'password' => 'abc', // shorter than PlainPassword::MIN_LENGTH
        ])->assertStatus(401)
          ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    });
});
