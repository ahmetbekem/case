<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\DeleteAddressCommand;
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

/**
 * ADR-018 — Soft delete + default re-pick policy.
 *
 * Eğer silinen adres default delivery ya da default billing ise,
 * o kullanıcının en son eklenen aktif adresini otomatik o slot'a
 * promote ederiz ("her zaman bir default olmalı" UX kuralı). Hiç
 * kalmadıysa default boş kalır (kullanıcı zaten yeni adres eklemek
 * zorunda kalacak).
 */
final class DeleteAddressHandler extends AbstractCommandHandler
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
        return DeleteAddressCommand::class;
    }

    protected function doHandle(CommandInterface $command): null
    {
        assert($command instanceof DeleteAddressCommand);

        $userId = UserId::fromString($command->getUserId());
        $address = $this->addressRepository->findByIdAndUser(
            AddressId::fromString($command->addressId),
            $userId,
        );
        if ($address === null) {
            throw AddressNotFoundException::withId($command->addressId);
        }

        $wasDefaultDelivery = $address->isDefaultDelivery();
        $wasDefaultBilling = $address->isDefaultBilling();

        $this->addressRepository->softDelete($address->getId());

        // Default re-pick — silinen adres default ise en son aktif adresi
        // otomatik promote et (kullanıcı yeni default seçmek zorunda
        // kalmasın). Hiç aktif adres kalmadıysa no-op.
        $promoted = null;
        if ($wasDefaultDelivery || $wasDefaultBilling) {
            $promoted = $this->addressRepository->findMostRecent($userId);
            if ($promoted !== null) {
                if ($wasDefaultDelivery) {
                    $promoted->setAsDefaultDelivery();
                }
                if ($wasDefaultBilling) {
                    $promoted->setAsDefaultBilling();
                }
                $this->addressRepository->save($promoted);
            }
        }

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $userId->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.address_deleted',
            resource: 'address:' . $address->getId()->value(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'wasDefaultDelivery' => $wasDefaultDelivery,
                'wasDefaultBilling' => $wasDefaultBilling,
                'promotedAddressId' => $promoted?->getId()->value(),
            ],
        ));

        return null;
    }
}
