<?php

declare(strict_types=1);

namespace Infrastructure\System\Repositories;

use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Entities\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Decorator — Eloquent repo'yu Redis cache'le sarar. find* okumaları
 * 5 dakika boyunca cache'ten döner; save() çağrısı tüm key cache'lerini
 * invalidate eder.
 *
 * Test env'de cache=array driver olduğundan Redis bağımlılığı yok.
 */
final class CachedSettingRepository implements SettingRepositoryInterface
{
    private const TTL_SECONDS = 300;
    private const KEY_PREFIX = 'system:settings:';

    public function __construct(
        private readonly SettingRepositoryInterface $inner,
        private readonly CacheRepository $cache,
    ) {
    }

    public function findByKey(string $key): ?Setting
    {
        $cacheKey = self::KEY_PREFIX . 'k:' . $key;
        return $this->cache->remember($cacheKey, self::TTL_SECONDS, function () use ($key) {
            return $this->inner->findByKey($key);
        });
    }

    public function findAll(): array
    {
        return $this->cache->remember(
            self::KEY_PREFIX . 'all',
            self::TTL_SECONDS,
            fn () => $this->inner->findAll(),
        );
    }

    public function findPublic(): array
    {
        return $this->cache->remember(
            self::KEY_PREFIX . 'public',
            self::TTL_SECONDS,
            fn () => $this->inner->findPublic(),
        );
    }

    public function save(Setting $setting): void
    {
        $this->inner->save($setting);
        $this->invalidate($setting->getKey());
    }

    private function invalidate(string $key): void
    {
        $this->cache->forget(self::KEY_PREFIX . 'k:' . $key);
        $this->cache->forget(self::KEY_PREFIX . 'all');
        $this->cache->forget(self::KEY_PREFIX . 'public');
    }
}
