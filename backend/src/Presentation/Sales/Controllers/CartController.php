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

/**
 * ADR-017 — Cart endpoint'leri optional.jwt + guest.session
 * middleware stack'i ile çağrılır. Authenticated user → user_id
 * attribute taşır; guest → guest_session_id attribute (cookie'den
 * veya yeni üretilmiş). Controller her endpoint'te bu ikisinden
 * birini command/query'e geçirir (XOR).
 */
final class CartController extends AbstractApiController
{
    #[OA\Get(
        path: '/sales/cart',
        summary: 'Sepet (user veya guest)',
        description: 'Aktif sepeti getirir. Guest için cookie üretilir/okunur. Boşsa boş sepet döner.',
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
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        [$userId, $guestSessionId] = $this->resolveOwner($request);

        $query = new GetCartQuery(
            userId: $userId,
            guestSessionId: $guestSessionId,
            correlationId: $this->getCorrelationId($request),
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Post(
        path: '/sales/cart/items',
        summary: 'Sepete ürün ekle (user veya guest)',
        description: 'Aynı productId zaten sepetteyse quantity\'leri toplar; '
            . 'fiyat snapshot\'ı ilk ekleme anındaki kalır.',
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
            new OA\Response(response: 404, description: 'Ürün bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Yetersiz stok / pasif ürün', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        [$userId, $guestSessionId] = $this->resolveOwner($request);

        $command = new AddItemToCartCommand(
            userId: $userId,
            guestSessionId: $guestSessionId,
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
            new OA\Response(response: 404, description: 'Sepet kalemi bulunamadı (IDOR korumalı 404)', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 409, description: 'Yetersiz stok', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function updateItem(UpdateCartItemRequest $request, string $itemId): JsonResponse
    {
        [$userId, $guestSessionId] = $this->resolveOwner($request);

        $command = new UpdateCartItemQuantityCommand(
            userId: $userId,
            guestSessionId: $guestSessionId,
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
            new OA\Response(response: 404, description: 'Sepet kalemi bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        [$userId, $guestSessionId] = $this->resolveOwner($request);

        $command = new RemoveCartItemCommand(
            userId: $userId,
            guestSessionId: $guestSessionId,
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
        ],
    )]
    public function clear(Request $request): JsonResponse
    {
        [$userId, $guestSessionId] = $this->resolveOwner($request);

        $command = new ClearCartCommand(
            userId: $userId,
            guestSessionId: $guestSessionId,
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    /**
     * Returns [userId, guestSessionId] — exactly one of them is non-null.
     * OptionalJwtMiddleware sets user_id when JWT is valid;
     * GuestSessionMiddleware sets guest_session_id otherwise.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveOwner(Request $request): array
    {
        $userId = $request->attributes->get('user_id');
        if (is_string($userId) && $userId !== '') {
            return [$userId, null];
        }

        $guestSessionId = $request->attributes->get('guest_session_id');
        if (!is_string($guestSessionId) || $guestSessionId === '') {
            // Middleware her zaman set etmeli — buraya düşmek
            // konfigürasyon hatası demektir.
            throw new \RuntimeException(
                'CartController invoked without user_id or guest_session_id; '
                . 'check optional.jwt + guest.session middleware on this route.'
            );
        }
        return [null, $guestSessionId];
    }
}
