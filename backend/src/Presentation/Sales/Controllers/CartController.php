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
use OpenApi\Attributes as OA;
use Presentation\Sales\Requests\AddCartItemRequest;
use Presentation\Sales\Requests\UpdateCartItemRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

final class CartController extends AbstractApiController
{
    #[OA\Get(
        path: '/sales/cart',
        summary: 'Kullanıcı sepeti',
        description: 'Aktif sepeti getirir. Henüz ürün eklenmemişse boş bir sepet döner.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sepet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CartDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $query = new GetCartQuery(
            userId: $this->getUserId($request),
            guestSessionId: null,
            correlationId: $this->getCorrelationId($request),
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Post(
        path: '/sales/cart/items',
        summary: 'Sepete ürün ekle',
        description: 'Aynı productId zaten sepetteyse quantity\'leri toplar; '
            . 'fiyat snapshot\'ı ilk ekleme anındaki kalır.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Cart'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddCartItemRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sepet güncel',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CartDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Yetersiz stok / pasif ürün', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $command = new AddItemToCartCommand(
            userId: $this->getUserId($request),
            guestSessionId: null,
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            productId: (string) $request->validated('productId'),
            quantity: (int) $request->validated('quantity'),
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    #[OA\Patch(
        path: '/sales/cart/items/{itemId}',
        summary: 'Sepet kaleminin quantity\'sini güncelle',
        description: 'quantity 0 olamaz — kaldırmak için DELETE endpoint\'ini kullan.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Cart'],
        parameters: [
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateCartItemRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sepet güncel',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CartDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Sepet kalemi bulunamadı (IDOR korumalı 404)', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Yetersiz stok', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function updateItem(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        $command = new UpdateCartItemQuantityCommand(
            userId: $this->getUserId($request),
            guestSessionId: null,
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            cartItemId: $itemId,
            quantity: (int) $request->validated('quantity'),
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    #[OA\Delete(
        path: '/sales/cart/items/{itemId}',
        summary: 'Sepet kaleminden tek ürünü çıkar',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Cart'],
        parameters: [
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sepet güncel',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CartDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Sepet kalemi bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $command = new RemoveCartItemCommand(
            userId: $this->getUserId($request),
            guestSessionId: null,
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            cartItemId: $itemId,
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    #[OA\Delete(
        path: '/sales/cart',
        summary: 'Sepeti tamamen boşalt',
        description: 'Tüm satırları siler ama sepetin kendisi (kayıt) kalır.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sepet boşaltıldı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/CartDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ],
    )]
    public function clear(Request $request): JsonResponse
    {
        $command = new ClearCartCommand(
            userId: $this->getUserId($request),
            guestSessionId: null,
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
        );
        return $this->success($this->commandBus->dispatch($command));
    }
}
