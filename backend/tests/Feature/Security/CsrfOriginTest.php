<?php

declare(strict_types=1);

describe('CsrfOriginMiddleware', function () {
    it('rejects POST without Origin or Referer with 403', function () {
        $this->withoutHeaders(['Origin'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'x@y.com',
                'password' => 'whatever',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CSRF_ORIGIN_MISSING');
    });

    it('rejects POST with Origin not in allowed_origins', function () {
        $this->withHeader('Origin', 'https://evil.example.com')
            ->postJson('/api/v1/auth/login', [
                'email' => 'x@y.com',
                'password' => 'whatever',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CSRF_ORIGIN_INVALID');
    });

    it('accepts POST with allowed Origin', function () {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'csrf@ok.local',
            'password' => 'secret123',
            'fullName' => 'CSRF OK',
        ])->assertCreated();
    });

    it('does not require Origin on GET (only state-changing methods)', function () {
        $session = registerAndLoginCustomer('safe-get@user.local');

        $this->withoutHeaders(['Origin'])
            ->getJson('/api/v1/auth/me', authHeaders($session['accessToken']))
            ->assertOk();
    });

    it('falls back to Referer when Origin header is missing', function () {
        $this->withoutHeaders(['Origin'])
            ->withHeader('Referer', 'http://localhost/some/page')
            ->postJson('/api/v1/auth/register', [
                'email' => 'referer@user.local',
                'password' => 'secret123',
                'fullName' => 'Referer Fallback',
            ])->assertCreated();
    });
});
