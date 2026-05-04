<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Services;

use Domain\Identity\Contracts\JwtBlacklistInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-backed JWT denylist. We use Laravel's cache abstraction (which
 * routes to Redis in dev/prod and to the array driver in tests per
 * phpunit.xml) instead of touching the Redis facade directly — that way
 * the in-memory test driver provides automatic per-test isolation
 * without any teardown.
 *
 * Keys are prefixed `jwt:blacklist:` and expire automatically via
 * the cache TTL — no manual sweep needed.
 */
final class RedisJwtBlacklist implements JwtBlacklistInterface
{
    private const string PREFIX = 'jwt:blacklist:';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {
    }

    public function add(string $jti, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }
        $this->cache->put(self::PREFIX . $jti, true, $ttlSeconds);
    }

    public function contains(string $jti): bool
    {
        return $this->cache->has(self::PREFIX . $jti);
    }
}
