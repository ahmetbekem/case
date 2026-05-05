<?php

declare(strict_types=1);

namespace Presentation\Currency\Controllers;

use Application\Currency\Queries\GetExchangeRateQuery;
use Application\Currency\Queries\ListSupportedCurrenciesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Shared\Abstracts\AbstractApiController;

final class CurrencyController extends AbstractApiController
{
    #[OA\Get(
        path: '/currencies',
        summary: 'Desteklenen para birimleri',
        description: 'Sabit liste — Currency enum\'undan türetilmiş code+symbol çiftleri. '
            . 'Public; auth gerekmez. throttle:api-read profili.',
        tags: ['Currency'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Para birimi listesi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SupportedCurrencyDTO'),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = new ListSupportedCurrenciesQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
        );

        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Get(
        path: '/currencies/{base}/{target}',
        summary: 'Tek kur',
        description: 'base → target arasındaki güncel kuru getirir. Redis cache\'ten okur, '
            . 'cache miss\'te Frankfurter API\'den çeker (TASK 0.10 scheduler 6 saatte bir refresh\'ler). '
            . 'base == target için identity (rate=1.0, provider="identity") döner. '
            . 'Public; auth gerekmez.',
        tags: ['Currency'],
        parameters: [
            new OA\Parameter(name: 'base', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['TRY', 'USD', 'EUR'])),
            new OA\Parameter(name: 'target', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['TRY', 'USD', 'EUR'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Exchange rate',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ExchangeRateDTO'),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'Geçersiz para birimi kodu veya kur bulunamadı', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')),
            new OA\Response(response: 429, description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/RateLimitError')),
        ],
    )]
    public function show(Request $request, string $base, string $target): JsonResponse
    {
        $query = new GetExchangeRateQuery(
            userId: $this->getUserId($request),
            correlationId: $this->getCorrelationId($request),
            base: $base,
            target: $target,
        );

        return $this->success($this->queryBus->ask($query));
    }
}
