<?php

declare(strict_types=1);

namespace App\Providers;

use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\JwtBlacklistInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Contracts\PasswordHasherInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Identity\Repositories\EloquentRefreshTokenRepository;
use Infrastructure\Identity\Repositories\EloquentUserRepository;
use Infrastructure\Identity\Services\BcryptPasswordHasher;
use Infrastructure\Identity\Services\HmacJwtIssuer;
use Infrastructure\Identity\Services\RedisJwtBlacklist;
use Infrastructure\Shared\Persistence\EloquentTransaction;

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
        // ─── Identity Repositories ───
        UserRepositoryInterface::class         => EloquentUserRepository::class,
        RefreshTokenRepositoryInterface::class => EloquentRefreshTokenRepository::class,

        // ─── Cross-cutting infrastructure ───
        TransactionInterface::class            => EloquentTransaction::class,

        // ─── JWT denylist (cache-backed; array driver in tests) ───
        JwtBlacklistInterface::class           => RedisJwtBlacklist::class,
    ];

    public function register(): void
    {
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
