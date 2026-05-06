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
use OpenApi\Attributes as OA;
use Presentation\Catalog\Requests\CreateProductRequest;
use Presentation\Catalog\Requests\ListProductsRequest;
use Presentation\Catalog\Requests\UpdateProductRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class ProductController extends AbstractApiController
{
    #[OA\Get(
        path: '/catalog/products',
        summary: 'Ürün listesi',
        description: 'Sayfalanmış ürün listesi. Public; auth gerekmez. '
            . 'pageSize server-side 100\'e cap edilir. Admin kullanıcı '
            . '`activeOnly=false` ile pasifleri de görebilir.',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'pageSize', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 20)),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Ürün adında arama', schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'activeOnly', in: 'query', required: false, description: 'Default true; admin false geçirebilir', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sayfalanmış liste',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductDTO')),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function index(ListProductsRequest $request): JsonResponse
    {
        $displayCurrencyParam = $request->input('displayCurrency');
        $query = new ListProductsQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            page: (int) $request->input('page', 1),
            pageSize: (int) $request->input('pageSize', 20),
            search: $request->input('search'),
            activeOnly: $request->boolean('activeOnly', true),
            displayCurrency: is_string($displayCurrencyParam) && $displayCurrencyParam !== ''
                ? strtoupper($displayCurrencyParam)
                : null,
        );

        $result = $this->queryBus->ask($query);

        return $this->success(
            data: $result['items'],
            meta: $result['meta'],
        );
    }

    #[OA\Get(
        path: '/catalog/products/{id}',
        summary: 'Ürün detay',
        description: 'Tek ürün kaydını döner. Public; auth gerekmez.',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ürün',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProductDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $displayCurrencyParam = $request->input('displayCurrency');
        $query = new GetProductQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            productId: $id,
            displayCurrency: is_string($displayCurrencyParam) && $displayCurrencyParam !== ''
                ? strtoupper($displayCurrencyParam)
                : null,
        );

        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Post(
        path: '/catalog/products',
        summary: 'Yeni ürün oluştur',
        description: 'Yalnızca admin rolü erişebilir. SKU otomatik UPPERCASE\'e çevrilir.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Catalog (Admin)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateProductRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Ürün oluşturuldu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProductDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Product created'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 409, description: 'Duplicate SKU', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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
            stock: (int) $request->validated('stock'),
        );

        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Product created',
            status: HttpStatus::CREATED,
        );
    }

    #[OA\Patch(
        path: '/catalog/products/{id}',
        summary: 'Ürün güncelle (partial)',
        description: 'Yalnızca admin. Tüm alanlar opsiyonel; gönderilenler güncellenir. '
            . 'isActive ile pasifleştirme/aktifleştirme aynı endpoint üzerinden.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Catalog (Admin)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateProductRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ürün güncellendi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProductDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Product updated'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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
            isActive: $request->has('isActive') ? (bool) $request->validated('isActive') : null,
        );

        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Product updated',
        );
    }

    #[OA\Delete(
        path: '/catalog/products/{id}',
        summary: 'Ürün sil (soft)',
        description: 'Yalnızca admin. Soft delete — kayıt audit zincirinde kalır.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Catalog (Admin)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Ürün silindi (no content)'),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
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

    #[OA\Post(
        path: '/catalog/products/{id}/stock',
        summary: 'Stok ayarla (delta)',
        description: 'Yalnızca admin. delta pozitif veya negatif int; '
            . 'sonuçta negatife düşerse 409 döner.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Catalog (Admin)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delta'],
                properties: [
                    new OA\Property(property: 'delta', type: 'integer', description: 'Pozitif (artır) veya negatif (azalt)', example: -5),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stok güncellendi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProductDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Stock adjusted'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Yetersiz stok (sonuç negatif)', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
        ],
    )]
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
