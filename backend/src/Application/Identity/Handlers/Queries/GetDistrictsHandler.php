<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Queries;

use Application\Identity\DTOs\DistrictDTO;
use Application\Identity\Queries\GetDistrictsQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Identity\Exceptions\InvalidLocationException;
use Domain\Identity\Services\TurkeyLocationsService;
use Psr\Log\LoggerInterface;

final class GetDistrictsHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly TurkeyLocationsService $locationsService,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetDistrictsQuery::class;
    }

    /**
     * @return array<int, DistrictDTO>
     */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof GetDistrictsQuery);

        if (!$this->locationsService->isValidCity($query->cityName)) {
            throw InvalidLocationException::invalidCity($query->cityName);
        }

        return array_map(
            static fn (string $name): DistrictDTO => new DistrictDTO(name: $name),
            $this->locationsService->getDistrictsByCity($query->cityName),
        );
    }
}
