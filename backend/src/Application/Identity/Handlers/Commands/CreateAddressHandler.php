<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\CreateAddressCommand;
use Application\Identity\DTOs\AddressDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Entities\Address;
use Domain\Identity\Exceptions\AddressLimitReachedException;
use Domain\Identity\Exceptions\InvalidLocationException;
use Domain\Identity\Services\TurkeyLocationsService;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Identity\ValueObjects\PhoneNumber;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * ADR-018 — Yeni adres ekleme.
 *
 * Akış:
 *   1) Limit kontrolü (max 10 aktif adres / kullanıcı)
 *   2) İl/ilçe doğrulaması (TurkeyLocationsService)
 *   3) Default flag set edilecekse eski default'u temizle
 *      (DB partial unique index'i tek default garantisi için zorunlu)
 *   4) İlk adres ise otomatik default delivery + billing yap (UX)
 *   5) Save + audit
 */
final class CreateAddressHandler extends AbstractCommandHandler
{
    /** UI sınırı; spec'te 10. Soft-deleted'lar sayılmaz. */
    public const int MAX_ADDRESSES_PER_USER = 10;

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
        return CreateAddressCommand::class;
    }

    protected function doHandle(CommandInterface $command): AddressDTO
    {
        assert($command instanceof CreateAddressCommand);

        $userId = UserId::fromString($command->getUserId());

        if ($this->addressRepository->countByUserId($userId) >= self::MAX_ADDRESSES_PER_USER) {
            throw AddressLimitReachedException::maxReached(self::MAX_ADDRESSES_PER_USER);
        }

        if (!$this->locationsService->isValidCity($command->city)) {
            throw InvalidLocationException::invalidCity($command->city);
        }
        if (!$this->locationsService->isValidDistrict($command->city, $command->district)) {
            throw InvalidLocationException::invalidDistrict($command->city, $command->district);
        }

        // Default flag set edilecekse eskiyi temizle (DB partial unique
        // index zaten tek default garanti ediyor — bu temizlik save()'in
        // constraint violation atmasını önlüyor).
        $isFirstAddress = $this->addressRepository->countByUserId($userId) === 0;
        $shouldBeDefaultDelivery = $command->setAsDefaultDelivery || $isFirstAddress;
        $shouldBeDefaultBilling = $command->setAsDefaultBilling || $isFirstAddress;

        if ($shouldBeDefaultDelivery) {
            $this->addressRepository->unsetAllDefaultDelivery($userId);
        }
        if ($shouldBeDefaultBilling) {
            $this->addressRepository->unsetAllDefaultBilling($userId);
        }

        $address = new Address(
            id: AddressId::generate(),
            userId: $userId,
            title: $command->title,
            fullName: $command->fullName,
            phone: PhoneNumber::fromString($command->phone),
            countryCode: Address::COUNTRY_TR,
            city: $command->city,
            district: $command->district,
            neighborhood: $command->neighborhood,
            postalCode: $command->postalCode,
            addressLine: $command->addressLine,
            addressLine2: $command->addressLine2,
            isDefaultDelivery: $shouldBeDefaultDelivery,
            isDefaultBilling: $shouldBeDefaultBilling,
        );

        $this->addressRepository->save($address);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $userId->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.address_created',
            resource: 'address:' . $address->getId()->value(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'city' => $address->getCity(),
                'district' => $address->getDistrict(),
                'isDefaultDelivery' => $address->isDefaultDelivery(),
                'isDefaultBilling' => $address->isDefaultBilling(),
            ],
        ));

        return AddressDTO::fromEntity($address);
    }
}
