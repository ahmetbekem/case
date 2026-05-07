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
use OpenApi\Attributes as OA;
use Presentation\Sales\Requests\CancelOrderRequest;
use Presentation\Sales\Requests\PlaceOrderRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class OrderController extends AbstractApiController
{
    #[OA\Get(
        path: '/sales/orders',
        summary: 'Sipariş listesi (kullanıcı kendi)',
        description: 'Default kullanıcı kendi siparişlerini görür. Admin kullanıcı '
            . '`adminScope=1` query param ile tüm kullanıcıların siparişlerini listeleyebilir; '
            . 'aksi takdirde admin de yalnızca kendi siparişlerini görür.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'pageSize', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 20)),
            new OA\Parameter(name: 'adminScope', in: 'query', required: false, description: 'Admin\'e özel — true geçerse tüm siparişleri listeler', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sayfalanmış sipariş listesi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderDTO')),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
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

    #[OA\Get(
        path: '/sales/orders/{id}',
        summary: 'Sipariş detay',
        description: 'Customer rolü yalnızca kendi siparişini görür. '
            . 'Sahibi olmadığı siparişe erişim **404** döner (IDOR pattern, anti-enumeration). '
            . 'Admin rolü tüm siparişleri görüntüleyebilir.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sipariş',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/OrderDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Sipariş bulunamadı (yetki yoksa da bu döner)', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
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

    #[OA\Post(
        path: '/sales/orders',
        summary: 'Sepetten sipariş oluştur',
        description: 'Aktif sepetteki tüm kalemleri yeni bir sipariş olarak dondurur. '
            . 'Sepet "converted" statüsüne geçer; ürün stokları atomic olarak düşürülür. '
            . 'Boş sepette 409 döner. throttle:order rate limit profili.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Orders'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PlaceOrderRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Sipariş oluşturuldu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/OrderDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Order placed'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 409, description: 'Boş sepet / yetersiz stok / pasif ürün', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $command = new PlaceOrderCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            displayCurrency: (string) $request->validated('displayCurrency'),
            deliveryAddressId: (string) $request->validated('deliveryAddressId'),
            billingAddressId: $request->validated('billingAddressId'),
            notes: $request->validated('notes'),
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Order placed',
            status: HttpStatus::CREATED,
        );
    }

    #[OA\Post(
        path: '/sales/orders/{id}/cancel',
        summary: 'Sipariş iptal',
        description: 'Yalnızca pending siparişler iptal edilebilir. Customer kendi siparişini, '
            . 'admin tüm siparişleri iptal edebilir. Sahibi olmayan customer\'a 404 döner '
            . '(IDOR pattern). Stoklar geri yazılır.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CancelOrderRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sipariş iptal edildi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/OrderDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Order cancelled'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Sipariş bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Sipariş zaten iptal/tamamlanmış', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error (reason eksik/kısa)', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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
