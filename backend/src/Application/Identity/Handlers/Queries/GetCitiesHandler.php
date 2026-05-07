<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Queries;

use Application\Identity\DTOs\CityDTO;
use Application\Identity\Queries\GetCitiesQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Identity\Services\TurkeyLocationsService;
use Psr\Log\LoggerInterface;

final class GetCitiesHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly TurkeyLocationsService $locationsService,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetCitiesQuery::class;
    }

    /**
     * @return array<int, CityDTO>
     */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof GetCitiesQuery);

        return array_map(
            static fn (array $c): CityDTO => new CityDTO(id: $c['id'], name: $c['name']),
            $this->locationsService->getAllCities(),
        );
    }
}
