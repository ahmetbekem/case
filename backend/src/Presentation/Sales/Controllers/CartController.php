<?php

declare(strict_types=1);

namespace Presentation\Sales\Controllers;

use Application\Sales\Commands\AddItemToCartCommand;
use Application\Sales\Commands\ClearCartCommand;
use Application\Sales\Commands\RemoveCartItemCommand;
use Application\Sales\Commands\UpdateCartItemQuantityCommand;
use Application\Sales\Queries\GetCartQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Sales\Requests\AddCartItemRequest;
use Presentation\Sales\Requests\UpdateCartItemRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class CartController extends AbstractApiController
{
    public function show(Request $request): JsonResponse
    {
        $query = new GetCartQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );
        return $this->success($this->queryBus->ask($query));
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $command = new AddItemToCartCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: (string) $request->validated('productId'),
            quantity: (int) $request->validated('quantity'),
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    public function updateItem(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        $command = new UpdateCartItemQuantityCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            cartItemId: $itemId,
            quantity: (int) $request->validated('quantity'),
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $command = new RemoveCartItemCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            cartItemId: $itemId,
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    public function clear(Request $request): JsonResponse
    {
        $command = new ClearCartCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
        );
        return $this->success($this->commandBus->dispatch($command));
    }
}
