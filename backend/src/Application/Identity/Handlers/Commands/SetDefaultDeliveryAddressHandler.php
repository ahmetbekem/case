<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\SetDefaultDeliveryAddressCommand;
use Application\Identity\DTOs\AddressDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Exceptions\AddressNotFoundException;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class SetDefaultDeliveryAddressHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return SetDefaultDeliveryAddressCommand::class;
    }

    protected function doHandle(CommandInterface $command): AddressDTO
    {
        assert($command instanceof SetDefaultDeliveryAddressCommand);

        $userId = UserId::fromString($command->getUserId());
        $address = $this->addressRepository->findByIdAndUser(
            AddressId::fromString($command->addressId),
            $userId,
        );
        if ($address === null) {
            throw AddressNotFoundException::withId($command->addressId);
        }

        if (!$address->isDefaultDelivery()) {
            $this->addressRepository->unsetAllDefaultDelivery($userId);
            $address->setAsDefaultDelivery();
            $this->addressRepository->save($address);

            $this->auditLogger->log(new AuditEvent(
                actor: 'userId:' . $userId->value(),
                actorIp: $command->getUserIp(),
                action: 'identity.address_set_default_delivery',
                resource: 'address:' . $address->getId()->value(),
                module: 'identity',
                result: 'success',
                correlationId: $command->getCorrelationId(),
            ));
        }

        return AddressDTO::fromEntity($address);
    }
}
