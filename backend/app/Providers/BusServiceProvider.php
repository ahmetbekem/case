<?php

declare(strict_types=1);

namespace App\Providers;

use Application\Identity\Commands\LoginUserCommand;
use Application\Identity\Commands\LogoutUserCommand;
use Application\Identity\Commands\RefreshTokenCommand;
use Application\Identity\Commands\RegisterUserCommand;
use Application\Identity\Handlers\Commands\LoginUserHandler;
use Application\Identity\Handlers\Commands\LogoutUserHandler;
use Application\Identity\Handlers\Commands\RefreshTokenHandler;
use Application\Identity\Handlers\Commands\RegisterUserHandler;
use Application\Identity\Handlers\Queries\GetCurrentUserHandler;
use Application\Identity\Queries\GetCurrentUserQuery;
use Application\Shared\Bus\CommandBus;
use Application\Shared\Bus\QueryBus;
use Application\Shared\Contracts\CommandBusInterface;
use Application\Shared\Contracts\QueryBusInterface;
use Illuminate\Support\ServiceProvider;

final class BusServiceProvider extends ServiceProvider
{
    /**
     * Command class → handler class. Catalog/Sales/Currency entries land
     * with their respective module TASKs (0.10 / 0.11 / 0.12).
     *
     * @var array<class-string, class-string>
     */
    protected array $commandHandlers = [
        // ─── Identity ───
        RegisterUserCommand::class => RegisterUserHandler::class,
        LoginUserCommand::class    => LoginUserHandler::class,
        RefreshTokenCommand::class => RefreshTokenHandler::class,
        LogoutUserCommand::class   => LogoutUserHandler::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    protected array $queryHandlers = [
        // ─── Identity ───
        GetCurrentUserQuery::class => GetCurrentUserHandler::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CommandBusInterface::class, function ($app) {
            return new CommandBus($app, $this->commandHandlers);
        });

        $this->app->singleton(QueryBusInterface::class, function ($app) {
            return new QueryBus($app, $this->queryHandlers);
        });
    }
}
