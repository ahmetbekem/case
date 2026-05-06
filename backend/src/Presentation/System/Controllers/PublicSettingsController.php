<?php

declare(strict_types=1);

namespace Presentation\System\Controllers;

use Application\System\Queries\GetAllSettingsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Shared\Abstracts\AbstractApiController;

final class PublicSettingsController extends AbstractApiController
{
    #[OA\Get(
        path: '/system/settings/public',
        summary: 'Public sistem ayarları',
        description: 'is_public=true ayarlar — base_currency, supported_currencies vb. '
            . 'Auth gerekmez; frontend boot anında çağırır.',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public ayar listesi',
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
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = new GetAllSettingsQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            publicOnly: true,
        );
        return $this->success($this->queryBus->ask($query));
    }
}
