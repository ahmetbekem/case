<?php

declare(strict_types=1);

namespace Presentation\Identity\Controllers;

use Application\Identity\Commands\CreateAddressCommand;
use Application\Identity\Commands\DeleteAddressCommand;
use Application\Identity\Commands\SetDefaultBillingAddressCommand;
use Application\Identity\Commands\SetDefaultDeliveryAddressCommand;
use Application\Identity\Commands\UpdateAddressCommand;
use Application\Identity\Queries\GetAddressQuery;
use Application\Identity\Queries\ListUserAddressesQuery;
use Domain\Shared\Constants\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Identity\Requests\CreateAddressRequest;
use Presentation\Identity\Requests\UpdateAddressRequest;
use Presentation\Shared\Abstracts\AbstractApiController;

/**
 * ADR-018 — Adres yönetimi (jwt.auth zorunlu).
 *
 * IDOR koruması: tüm detail/update/delete endpoint'leri repository
 * findByIdAndUser'a düşer; başka kullanıcının adresine erişim 404.
 */
final class AddressController extends AbstractApiController
{
    #[OA\Get(
        path: '/identity/addresses',
        summary: 'Kullanıcı adres listesi',
        description: 'Kullanıcının tüm aktif adresleri (soft-delete\'ler hariç). Sıralama: '
            . 'önce default delivery, sonra default billing, en son createdAt DESC.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Adres listesi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/AddressDTO'),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = new ListUserAddressesQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Get(
        path: '/identity/addresses/{id}',
        summary: 'Adres detay',
        description: 'IDOR-protected: başka kullanıcının adresi de 404 (anti-enumeration).',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Adres',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AddressDTO')],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 404, description: 'Adres bulunamadı (IDOR-safe)', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $query = new GetAddressQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            addressId: $id,
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Post(
        path: '/identity/addresses',
        summary: 'Yeni adres ekle',
        description: 'Limit: aktif 10 adres / kullanıcı. İlk adres otomatik default delivery + billing. '
            . 'İl/ilçe TurkeyLocationsService\'e karşı validate edilir.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateAddressRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Adres oluşturuldu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/AddressDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Address created'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 409, description: 'Adres limiti dolu', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation veya il/ilçe hatası', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(CreateAddressRequest $request): JsonResponse
    {
        $command = new CreateAddressCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            title: (string) $request->validated('title'),
            fullName: (string) $request->validated('fullName'),
            phone: (string) $request->validated('phone'),
            city: (string) $request->validated('city'),
            district: (string) $request->validated('district'),
            neighborhood: $request->validated('neighborhood'),
            postalCode: $request->validated('postalCode'),
            addressLine: (string) $request->validated('addressLine'),
            addressLine2: $request->validated('addressLine2'),
            setAsDefaultDelivery: (bool) $request->validated('setAsDefaultDelivery', false),
            setAsDefaultBilling: (bool) $request->validated('setAsDefaultBilling', false),
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Address created',
            status: HttpStatus::CREATED,
        );
    }

    #[OA\Put(
        path: '/identity/addresses/{id}',
        summary: 'Adres güncelle',
        description: 'IDOR-protected. Default flag\'ler bu endpoint ile değişmez.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateAddressRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Adres güncellendi',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AddressDTO')],
                ),
            ),
            new OA\Response(response: 404, description: 'Adres bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Validation veya il/ilçe hatası', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateAddressRequest $request, string $id): JsonResponse
    {
        $command = new UpdateAddressCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            addressId: $id,
            title: (string) $request->validated('title'),
            fullName: (string) $request->validated('fullName'),
            phone: (string) $request->validated('phone'),
            city: (string) $request->validated('city'),
            district: (string) $request->validated('district'),
            neighborhood: $request->validated('neighborhood'),
            postalCode: $request->validated('postalCode'),
            addressLine: (string) $request->validated('addressLine'),
            addressLine2: $request->validated('addressLine2'),
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Address updated',
        );
    }

    #[OA\Delete(
        path: '/identity/addresses/{id}',
        summary: 'Adres sil (soft delete)',
        description: 'Sipariş history snapshot\'ları korunur. Silinen adres default ise '
            . 'en son aktif adres otomatik default\'a promote olur.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Silindi'),
            new OA\Response(response: 404, description: 'Adres bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $command = new DeleteAddressCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            addressId: $id,
        );
        $this->commandBus->dispatch($command);
        return $this->noContent();
    }

    #[OA\Patch(
        path: '/identity/addresses/{id}/default-delivery',
        summary: 'Bu adresi varsayılan teslimat yap',
        description: 'Idempotent — zaten default ise no-op. Diğer adresin default delivery flag\'i unset edilir.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Varsayılan teslimat ayarlandı',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AddressDTO')],
                ),
            ),
            new OA\Response(response: 404, description: 'Adres bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function setDefaultDelivery(Request $request, string $id): JsonResponse
    {
        $command = new SetDefaultDeliveryAddressCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            addressId: $id,
        );
        return $this->success($this->commandBus->dispatch($command));
    }

    #[OA\Patch(
        path: '/identity/addresses/{id}/default-billing',
        summary: 'Bu adresi varsayılan fatura yap',
        description: 'Idempotent — zaten default ise no-op. Diğer adresin default billing flag\'i unset edilir.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Address'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Varsayılan fatura ayarlandı',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AddressDTO')],
                ),
            ),
            new OA\Response(response: 404, description: 'Adres bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
        ],
    )]
    public function setDefaultBilling(Request $request, string $id): JsonResponse
    {
        $command = new SetDefaultBillingAddressCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            addressId: $id,
        );
        return $this->success($this->commandBus->dispatch($command));
    }
}
