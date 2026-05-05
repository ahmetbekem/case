<?php

declare(strict_types=1);

namespace App\Providers;

use Application\Catalog\Commands\AdjustStockCommand;
use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\Commands\DeleteProductCommand;
use Application\Catalog\Commands\UpdateProductCommand;
use Application\Catalog\Handlers\Commands\AdjustStockHandler;
use Application\Catalog\Handlers\Commands\CreateProductHandler;
use Application\Catalog\Handlers\Commands\DeleteProductHandler;
use Application\Catalog\Handlers\Commands\UpdateProductHandler;
use Application\Catalog\Handlers\Queries\GetProductHandler;
use Application\Catalog\Handlers\Queries\ListProductsHandler;
use Application\Catalog\Queries\GetProductQuery;
use Application\Catalog\Queries\ListProductsQuery;
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

        // ─── Catalog ───
        CreateProductCommand::class => CreateProductHandler::class,
        UpdateProductCommand::class => UpdateProductHandler::class,
        DeleteProductCommand::class => DeleteProductHandler::class,
        AdjustStockCommand::class   => AdjustStockHandler::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    protected array $queryHandlers = [
        // ─── Identity ───
        GetCurrentUserQuery::class => GetCurrentUserHandler::class,

        // ─── Catalog ───
        GetProductQuery::class     => GetProductHandler::class,
        ListProductsQuery::class   => ListProductsHandler::class,
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
