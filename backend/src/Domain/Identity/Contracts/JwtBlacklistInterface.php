<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

/**
 * JWT denylist contract — backed by a fast key-value store (Redis in
 * production) keyed by the token's `jti` claim. Logout adds the access
 * token's jti for the rest of its lifetime; JwtAuthMiddleware checks
 * every request.
 *
 * @see SECURITY.md §4.6
 */
interface JwtBlacklistInterface
{
    /**
     * Add a JWT id to the blacklist for the given TTL (seconds).
     * No-op if $ttlSeconds <= 0.
     */
    public function add(string $jti, int $ttlSeconds): void;

    public function contains(string $jti): bool;
}
