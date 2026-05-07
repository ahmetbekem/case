<?php

declare(strict_types=1);

namespace Presentation\System\Controllers;

use Application\System\Commands\ChangeBaseCurrencyCommand;
use Application\System\Commands\UpdateSettingCommand;
use Application\System\Queries\GetAllSettingsQuery;
use Domain\System\ValueObjects\SettingKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Shared\Abstracts\AbstractApiController;
use Presentation\System\Requests\UpdateSettingRequest;

final class AdminSettingsController extends AbstractApiController
{
    #[OA\Get(
        path: '/admin/system/settings',
        summary: 'Tüm sistem ayarları (admin)',
        description: 'is_public ayrımı yapmadan tüm settings listesi.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['System (Admin)'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tüm ayarlar',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SettingDTO'),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = new GetAllSettingsQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            publicOnly: false,
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Patch(
        path: '/admin/system/settings/{key}',
        summary: 'Ayar değeri güncelle',
        description: 'Yeni ayar yaratmaz — yalnızca mevcut key\'in value\'sunu günceller. '
            . 'Cache otomatik invalidate edilir; sonraki public/admin GET fresh okur. '
            . 'audit log: system.setting_update (oldValue + newValue).',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['System (Admin)'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'base_currency')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSettingRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Güncel ayar',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SettingDTO'),
                        new OA\Property(property: 'message', type: 'string', example: 'Setting updated'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Auth gerekli', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Admin değil', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 404, description: 'Ayar bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $value = (string) $request->validated('value');

        // ADR-016 — base_currency yalnızca raw setting update değil; tüm
        // catalog.products fiyatlarını yeniden hesaplamayı + aktif
        // cart'ları abandoned'a çekmeyi gerektirir. Frontend SweetAlert
        // ile onay aldıktan sonra bu endpoint'i çağırır.
        if ($key === SettingKey::BASE_CURRENCY) {
            $changeCommand = new ChangeBaseCurrencyCommand(
                userId: $this->getUserId($request),
                correlationId: $this->getCorrelationId($request),
                userIp: $request->ip() ?? '0.0.0.0',
                newBaseCurrency: $value,
            );
            return $this->success(
                data: $this->commandBus->dispatch($changeCommand),
                message: 'Base currency changed; products repriced',
            );
        }

        $command = new UpdateSettingCommand(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            userIp: $request->ip() ?? '0.0.0.0',
            key: $key,
            value: $value,
        );
        return $this->success(
            data: $this->commandBus->dispatch($command),
            message: 'Setting updated',
        );
    }
}
