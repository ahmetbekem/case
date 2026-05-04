<?php

declare(strict_types=1);

describe('JWT blacklist on logout', function () {
    it('logging out blacklists the access token jti — subsequent /me → 401', function () {
        $session = registerAndLoginCustomer('blacklist@user.local');

        // Token is currently valid
        $this->getJson('/api/v1/auth/me', authHeaders($session['accessToken']))
            ->assertOk();

        // Logout — backend records the access token jti in the cache-backed denylist
        $this->postJson(
            '/api/v1/auth/logout',
            ['refresh_token' => $session['refreshToken']],
            authHeaders($session['accessToken']),
        )->assertNoContent();

        // Same access token now rejected even though its `exp` claim is still in the future
        $this->getJson('/api/v1/auth/me', authHeaders($session['accessToken']))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_ACCESS_TOKEN');
    });

    it('a fresh login after logout produces a working token', function () {
        registerAndLoginCustomer('rebound@user.local');

        // Login again → fresh access token, fresh jti — must not be blacklisted
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'rebound@user.local',
            'password' => 'secret123',
        ])->assertOk();

        $this->getJson('/api/v1/auth/me', authHeaders($login->json('data.accessToken')))
            ->assertOk();
    });
});
