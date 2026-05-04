<?php

declare(strict_types=1);

describe('ProxyOriginMiddleware', function () {
    it('rejects API requests without X-Proxy-Secret', function () {
        $this->withoutHeaders(['X-Proxy-Secret'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'x@y.com',
                'password' => 'whatever',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PROXY_BYPASS');
    });

    it('rejects API requests with a wrong X-Proxy-Secret', function () {
        $this->withHeader('X-Proxy-Secret', 'totally-wrong-value')
            ->postJson('/api/v1/auth/login', [
                'email' => 'x@y.com',
                'password' => 'whatever',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PROXY_BYPASS');
    });

    it('accepts requests carrying the correct X-Proxy-Secret', function () {
        // TestCase setUp already wires the matching header; confirm the
        // happy path: a normal register call reaches the handler (201).
        $this->postJson('/api/v1/auth/register', [
            'email' => 'proxy@ok.local',
            'password' => 'secret123',
            'fullName' => 'Proxy OK',
        ])->assertCreated();
    });
});
