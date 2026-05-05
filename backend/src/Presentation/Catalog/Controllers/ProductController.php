<?php

declare(strict_types=1);

namespace Presentation\Catalog\Controllers;

use Application\Catalog\Commands\AdjustStockCommand;
use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\Commands\DeleteProductCommand;
use Application\Catalog\Commands\UpdateProductCommand;
use Application\Catalog\Queries\GetProductQuery;
use Application\Catalog\Queries\ListProductsQuery;
use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Presentation\Catalog\Requests\CreateProductRequest;
use Presentation\Catalog\Requests\ListProductsRequest;
use Presentation\Catalog\Requests\UpdateProductRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class ProductController extends AbstractApiController
{
    public function index(ListProductsRequest $request): JsonResponse
    {
        $query = new ListProductsQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            page: (int) $request->input('page', 1),
            pageSize: (int) $request->input('pageSize', 20),
            search: $request->input('search'),
            activeOnly: $request->boolean('activeOnly', true),
        );

        $result = $this->queryBus->ask($query);

        return $this->success(
            data: $result['items'],
            meta: $result['meta'],
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = new GetProductQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            productId: $id,
        );

        return $this->success($this->queryBus->ask($query));
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $command = new CreateProductCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            sku: (string) $request->validated('sku'),
            name: (string) $request->validated('name'),
            description: $request->validated('description'),
            price: (float) $request->validated('price'),
            currency: (string) $request->validated('currency', 'TRY'),
            stock: (int) $request->validated('stock'),
        );

        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Product created',
            status: HttpStatus::CREATED,
        );
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $command = new UpdateProductCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: $id,
            name: $request->validated('name'),
            description: $request->has('description') ? $request->validated('description') : null,
            price: $request->validated('price') !== null ? (float) $request->validated('price') : null,
            currency: $request->validated('currency'),
            isActive: $request->has('isActive') ? (bool) $request->validated('isActive') : null,
        );

        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Product updated',
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $command = new DeleteProductCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: $id,
        );

        $this->commandBus->dispatch($command);

        return $this->noContent();
    }

    public function adjustStock(Request $request, string $id): JsonResponse
    {
        $delta = (int) $request->input('delta', 0);

        $command = new AdjustStockCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: $id,
            delta: $delta,
        );

        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Stock adjusted',
        );
    }
}
