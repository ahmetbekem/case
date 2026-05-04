<?php

declare(strict_types=1);

use Domain\Identity\Entities\RefreshToken;
use Infrastructure\Identity\Models\RefreshTokenModel;

describe('POST /api/v1/auth/refresh', function () {
    it('rotates the refresh token and returns a fresh pair', function () {
        $session = registerAndLoginCustomer();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $session['refreshToken'],
        ])->assertOk();

        $newAccess = $response->json('data.accessToken');
        $newRefresh = $response->json('data.refreshToken');

        expect($newAccess)->toBeString()->not->toBe($session['accessToken'])
            ->and($newRefresh)->toMatch('/^[a-f0-9]{64}$/')->not->toBe($session['refreshToken']);
    });

    it('rejects malformed refresh tokens with 422', function () {
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'not-hex',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['refresh_token']);
    });

    it('rejects unknown refresh tokens with 401 INVALID_REFRESH_TOKEN', function () {
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => str_repeat('0', 64),
        ])->assertStatus(401)
          ->assertJsonPath('error.code', 'INVALID_REFRESH_TOKEN');
    });

    it('detects replay and revokes the entire family', function () {
        $session = registerAndLoginCustomer();
        $stolen = $session['refreshToken'];

        // First use — succeeds, marks $stolen as rotated
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $stolen,
        ])->assertOk();

        // Force the rotated token's revokedAt past the grace window
        $hash = hash('sha256', $stolen);
        RefreshTokenModel::query()
            ->where('token_hash', $hash)
            ->update([
                'revoked_at' => now()->subSeconds(RefreshToken::ROTATION_GRACE_SECONDS + 1),
            ]);

        // Second use — replay! family revoked + 401 REFRESH_TOKEN_REPLAY
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $stolen,
        ])->assertStatus(401);

        expect($response->json('error.code'))->toBe('REFRESH_TOKEN_REPLAY')
            ->and($response->json('error.context.userId'))->toBe($session['userId']);

        // All tokens in the family must now be revoked
        $familyId = RefreshTokenModel::query()->where('token_hash', $hash)->value('family_id');
        $activeInFamily = RefreshTokenModel::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->count();
        expect($activeInFamily)->toBe(0);
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('returns 204 and revokes the family', function () {
        $session = registerAndLoginCustomer();

        $this->postJson(
            '/api/v1/auth/logout',
            ['refresh_token' => $session['refreshToken']],
            authHeaders($session['accessToken']),
        )->assertNoContent();

        // The original refresh should now be revoked
        $hash = hash('sha256', $session['refreshToken']);
        $revokedAt = RefreshTokenModel::query()
            ->where('token_hash', $hash)
            ->value('revoked_at');
        expect($revokedAt)->not->toBeNull();
    });

    it('subsequent refresh with the now-revoked token (past grace) → REPLAY', function () {
        $session = registerAndLoginCustomer();

        $this->postJson(
            '/api/v1/auth/logout',
            ['refresh_token' => $session['refreshToken']],
            authHeaders($session['accessToken']),
        )->assertNoContent();

        // Push revokedAt back so it's past grace (logout reason has no grace anyway,
        // but isRevoked() would already return true; this just makes intent explicit)
        $hash = hash('sha256', $session['refreshToken']);
        RefreshTokenModel::query()
            ->where('token_hash', $hash)
            ->update([
                'revoked_at' => now()->subSeconds(RefreshToken::ROTATION_GRACE_SECONDS + 1),
            ]);

        // Now refreshing with the logged-out token → triggers replay path
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $session['refreshToken'],
        ])->assertStatus(401)
          ->assertJsonPath('error.code', 'REFRESH_TOKEN_REPLAY');
    });
});
