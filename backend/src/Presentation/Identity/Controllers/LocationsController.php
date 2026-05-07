<?php

declare(strict_types=1);

namespace Presentation\Identity\Controllers;

use Application\Identity\Queries\GetCitiesQuery;
use Application\Identity\Queries\GetDistrictsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Presentation\Shared\Abstracts\AbstractApiController;

/**
 * ADR-018 — Türkiye il/ilçe statik veri endpoint'leri. Public —
 * auth gerekmez (form dropdown'larından çağrılır, login öncesi de
 * accessible).
 */
final class LocationsController extends AbstractApiController
{
    #[OA\Get(
        path: '/locations/cities',
        summary: 'Türkiye il listesi',
        description: 'Statik veri (TurkeyLocationsService); 81 il. Public endpoint.',
        tags: ['Locations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'İl listesi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/CityDTO'),
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function cities(Request $request): JsonResponse
    {
        $query = new GetCitiesQuery(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
        );
        return $this->success($this->queryBus->ask($query));
    }

    #[OA\Get(
        path: '/locations/cities/{city}/districts',
        summary: 'İl ilçeleri',
        description: 'Verilen ilin tüm ilçeleri. Geçersiz şehir adı 422 INVALID_CITY.',
        tags: ['Locations'],
        parameters: [
            new OA\Parameter(name: 'city', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'İstanbul'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'İlçe listesi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/DistrictDTO'),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Geçersiz şehir', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function districts(Request $request, string $city): JsonResponse
    {
        $query = new GetDistrictsQuery(
            userId: 'anonymous',
            correlationId: $this->getCorrelationId($request),
            cityName: rawurldecode($city),
        );
        return $this->success($this->queryBus->ask($query));
    }
}
