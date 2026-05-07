<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\UpdateAddressCommand;
use Application\Identity\DTOs\AddressDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Exceptions\AddressNotFoundException;
use Domain\Identity\Exceptions\InvalidLocationException;
use Domain\Identity\Services\TurkeyLocationsService;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Identity\ValueObjects\PhoneNumber;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * ADR-018 — Adres güncelleme. IDOR koruması: findByIdAndUser.
 * Default flag'ler bu endpoint ile DEĞİŞMEZ — ayrı endpoint'leri var.
 */
final class UpdateAddressHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly TurkeyLocationsService $locationsService,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return UpdateAddressCommand::class;
    }

    protected function doHandle(CommandInterface $command): AddressDTO
    {
        assert($command instanceof UpdateAddressCommand);

        $userId = UserId::fromString($command->getUserId());
        $address = $this->addressRepository->findByIdAndUser(
            AddressId::fromString($command->addressId),
            $userId,
        );
        if ($address === null) {
            throw AddressNotFoundException::withId($command->addressId);
        }

        if (!$this->locationsService->isValidCity($command->city)) {
            throw InvalidLocationException::invalidCity($command->city);
        }
        if (!$this->locationsService->isValidDistrict($command->city, $command->district)) {
            throw InvalidLocationException::invalidDistrict($command->city, $command->district);
        }

        $address->update(
            title: $command->title,
            fullName: $command->fullName,
            phone: PhoneNumber::fromString($command->phone),
            city: $command->city,
            district: $command->district,
            neighborhood: $command->neighborhood,
            postalCode: $command->postalCode,
            addressLine: $command->addressLine,
            addressLine2: $command->addressLine2,
        );

        $this->addressRepository->save($address);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $userId->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.address_updated',
            resource: 'address:' . $address->getId()->value(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'city' => $address->getCity(),
                'district' => $address->getDistrict(),
            ],
        ));

        return AddressDTO::fromEntity($address);
    }
}
