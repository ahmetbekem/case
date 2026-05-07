<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Queries;

use Application\Identity\DTOs\AddressDTO;
use Application\Identity\Queries\GetAddressQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Exceptions\AddressNotFoundException;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * IDOR-protected — başka kullanıcının adresi de 404 (anti-enumeration).
 */
final class GetAddressHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetAddressQuery::class;
    }

    protected function doHandle(QueryInterface $query): AddressDTO
    {
        assert($query instanceof GetAddressQuery);

        $address = $this->addressRepository->findByIdAndUser(
            AddressId::fromString($query->addressId),
            UserId::fromString($query->getUserId()),
        );
        if ($address === null) {
            throw AddressNotFoundException::withId($query->addressId);
        }
        return AddressDTO::fromEntity($address);
    }
}
