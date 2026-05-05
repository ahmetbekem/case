<?php

declare(strict_types=1);

namespace Presentation\Sales\Controllers;

use Application\Sales\Commands\CancelOrderCommand;
use Application\Sales\Commands\PlaceOrderCommand;
use Application\Sales\Queries\GetOrderQuery;
use Application\Sales\Queries\ListUserOrdersQuery;
use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Sales\Requests\CancelOrderRequest;
use Presentation\Sales\Requests\PlaceOrderRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class OrderController extends AbstractApiController
{
    public function index(Request $request): JsonResponse
    {
        $userRole = $request->attributes->get('user_role');
        $query = new ListUserOrdersQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            page: (int) $request->input('page', 1),
            pageSize: (int) $request->input('pageSize', 20),
            userRole: is_string($userRole) ? $userRole : null,
            adminScope: $request->boolean('adminScope'),
        );
        $result = $this->queryBus->ask($query);
        return $this->success(data: $result['items'], meta: $result['meta']);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userRole = $request->attributes->get('user_role');
        $query = new GetOrderQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            orderId: $id,
            userRole: is_string($userRole) ? $userRole : null,
        );
        return $this->success($this->queryBus->ask($query));
    }

    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $command = new PlaceOrderCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            displayCurrency: (string) $request->validated('displayCurrency'),
            notes: $request->validated('notes'),
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Order placed',
            status: HttpStatus::CREATED,
        );
    }

    public function cancel(CancelOrderRequest $request, string $id): JsonResponse
    {
        $userRole = $request->attributes->get('user_role');
        $command = new CancelOrderCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            orderId: $id,
            reason: (string) $request->validated('reason'),
            userRole: is_string($userRole) ? $userRole : null,
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Order cancelled',
        );
    }
}
