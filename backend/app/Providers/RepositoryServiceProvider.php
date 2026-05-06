<?php

declare(strict_types=1);

namespace App\Providers;

use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Services\CurrencyConverter;
use Domain\Identity\Contracts\JwtBlacklistInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Contracts\PasswordHasherInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\System\Contracts\SettingRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Catalog\Repositories\EloquentProductRepository;
use Infrastructure\Currency\Providers\FrankfurterApiProvider;
use Infrastructure\Currency\Repositories\EloquentExchangeRateRepository;
use Infrastructure\Identity\Repositories\EloquentRefreshTokenRepository;
use Infrastructure\Identity\Repositories\EloquentUserRepository;
use Infrastructure\Identity\Services\BcryptPasswordHasher;
use Infrastructure\Identity\Services\HmacJwtIssuer;
use Infrastructure\Identity\Services\RedisJwtBlacklist;
use Infrastructure\Sales\Repositories\EloquentCartRepository;
use Infrastructure\Sales\Repositories\EloquentOrderRepository;
use Infrastructure\Shared\Persistence\EloquentTransaction;
use Infrastructure\System\Mappers\SettingMapper;
use Infrastructure\System\Repositories\CachedSettingRepository;
use Infrastructure\System\Repositories\EloquentSettingRepository;

final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface → implementation map for direct, parameterless bindings.
     * Laravel's ServiceProvider base auto-registers everything in this
     * `public $bindings` property — no register() loop needed.
     *
     * Services that need config-driven construction (HmacJwtIssuer,
     * BcryptPasswordHasher) live in register() as closure singletons.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class         => EloquentUserRepository::class,
        RefreshTokenRepositoryInterface::class => EloquentRefreshTokenRepository::class,

        ProductRepositoryInterface::class      => EloquentProductRepository::class,

        ExchangeRateRepositoryInterface::class => EloquentExchangeRateRepository::class,
        ExchangeRateProviderInterface::class   => FrankfurterApiProvider::class,
        // CurrencyConverter is a stateless final readonly service — Container
        // can autowire it directly; we list it for explicitness.
        CurrencyConverter::class               => CurrencyConverter::class,

        CartRepositoryInterface::class         => EloquentCartRepository::class,
        OrderRepositoryInterface::class        => EloquentOrderRepository::class,

        TransactionInterface::class            => EloquentTransaction::class,

        JwtBlacklistInterface::class           => RedisJwtBlacklist::class,
    ];

    public function register(): void
    {
        // SettingRepository — Eloquent + Redis cache decorator (5 dk TTL).
        // Test env'de cache=array driver, prod'da redis.
        $this->app->singleton(SettingRepositoryInterface::class, function ($app) {
            return new CachedSettingRepository(
                inner: new EloquentSettingRepository($app->make(SettingMapper::class)),
                cache: $app->make(CacheRepository::class),
            );
        });

        // Identity services with config-driven construction
        $this->app->singleton(PasswordHasherInterface::class, function () {
            return new BcryptPasswordHasher(
                cost: (int) config('hashing.bcrypt.rounds', 12),
            );
        });

        $this->app->singleton(JwtIssuerInterface::class, function () {
            return new HmacJwtIssuer(
                secret: (string) config('jwt.secret'),
                issuer: (string) config('app.name', 'dehasoft-api'),
                audience: (string) config('jwt.audience', 'dehasoft-app'),
                ttlMinutes: (int) config('jwt.ttl', 15),
            );
        });
    }
}
