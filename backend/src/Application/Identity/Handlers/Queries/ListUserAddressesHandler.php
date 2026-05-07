<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Queries;

use Application\Identity\DTOs\AddressDTO;
use Application\Identity\Queries\ListUserAddressesQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class ListUserAddressesHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return ListUserAddressesQuery::class;
    }

    /**
     * @return array<int, AddressDTO>
     */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof ListUserAddressesQuery);

        $addresses = $this->addressRepository->findByUserId(
            UserId::fromString($query->getUserId()),
        );
        return AddressDTO::collection($addresses);
    }
}
