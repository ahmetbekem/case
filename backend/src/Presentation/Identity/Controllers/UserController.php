<?php

declare(strict_types=1);

namespace Presentation\Identity\Controllers;

use Application\Identity\Queries\GetCurrentUserQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Shared\Abstracts\AbstractApiController;

final class UserController extends AbstractApiController
{
    #[OA\Get(
        path: '/auth/me',
        summary: 'Mevcut kullanıcı bilgisi',
        description: 'Cookie/Bearer token ile çağrılır; ilgili kullanıcının kendi UserDTO\'sunu döner.',
        security: [['cookieAuth' => []], ['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mevcut kullanıcı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/UserDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Token gerekli/geçersiz', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        $query = new GetCurrentUserQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );

        $result = $this->queryBus->ask($query);

        return $this->success($result);
    }
}
