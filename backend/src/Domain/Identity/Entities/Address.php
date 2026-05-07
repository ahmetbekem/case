<?php

declare(strict_types=1);

namespace Domain\Identity\Entities;

use Domain\Identity\ValueObjects\AddressId;
use Domain\Identity\ValueObjects\PhoneNumber;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\UserId;

/**
 * ADR-018 — Kullanıcı adres rehberi entry'si. User aggregate dışında
 * ama Identity bounded context içinde — User ile lifecycle'ı bağımsız
 * (cascade delete YOK; soft delete var).
 *
 * toSnapshot() PlaceOrderHandler tarafından çağrılır; sipariş JSONB
 * sütununa immutable olarak yazılır. Adres sonradan güncellense ya
 * da silinse bile sipariş bozulmaz.
 */
final class Address extends AbstractEntity
{
    public const string COUNTRY_TR = 'TR';
    public const int TITLE_MAX_LENGTH = 50;
    public const int ADDRESS_LINE_MIN_LENGTH = 10;

    public function __construct(
        private readonly AddressId $id,
        private readonly UserId $userId,
        private string $title,
        private string $fullName,
        private PhoneNumber $phone,
        private string $countryCode,
        private string $city,
        private string $district,
        private ?string $neighborhood,
        private ?string $postalCode,
        private string $addressLine,
        private ?string $addressLine2,
        private bool $isDefaultDelivery,
        private bool $isDefaultBilling,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->validate();
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    private function validate(): void
    {
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('Address title is required');
        }
        if (mb_strlen($this->title) > self::TITLE_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                'Address title too long (max ' . self::TITLE_MAX_LENGTH . ' chars)'
            );
        }
        if (trim($this->fullName) === '') {
            throw new \InvalidArgumentException('Address full name is required');
        }
        if (mb_strlen($this->addressLine) < self::ADDRESS_LINE_MIN_LENGTH) {
            throw new \InvalidArgumentException(
                'Address line too short (min ' . self::ADDRESS_LINE_MIN_LENGTH . ' chars)'
            );
        }
        if ($this->countryCode !== self::COUNTRY_TR) {
            throw new \InvalidArgumentException(
                "Only '" . self::COUNTRY_TR . "' country code is supported"
            );
        }
        if (trim($this->city) === '' || trim($this->district) === '') {
            throw new \InvalidArgumentException('City and district are required');
        }
    }

    public function getId(): AddressId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getPhone(): PhoneNumber
    {
        return $this->phone;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getDistrict(): string
    {
        return $this->district;
    }

    public function getNeighborhood(): ?string
    {
        return $this->neighborhood;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function isDefaultDelivery(): bool
    {
        return $this->isDefaultDelivery;
    }

    public function isDefaultBilling(): bool
    {
        return $this->isDefaultBilling;
    }

    public function update(
        string $title,
        string $fullName,
        PhoneNumber $phone,
        string $city,
        string $district,
        ?string $neighborhood,
        ?string $postalCode,
        string $addressLine,
        ?string $addressLine2,
    ): void {
        $this->title = $title;
        $this->fullName = $fullName;
        $this->phone = $phone;
        $this->city = $city;
        $this->district = $district;
        $this->neighborhood = $neighborhood;
        $this->postalCode = $postalCode;
        $this->addressLine = $addressLine;
        $this->addressLine2 = $addressLine2;
        $this->validate();
        $this->markAsUpdated();
    }

    public function setAsDefaultDelivery(): void
    {
        if ($this->isDefaultDelivery) {
            return;
        }
        $this->isDefaultDelivery = true;
        $this->markAsUpdated();
    }

    public function unsetDefaultDelivery(): void
    {
        if (!$this->isDefaultDelivery) {
            return;
        }
        $this->isDefaultDelivery = false;
        $this->markAsUpdated();
    }

    public function setAsDefaultBilling(): void
    {
        if ($this->isDefaultBilling) {
            return;
        }
        $this->isDefaultBilling = true;
        $this->markAsUpdated();
    }

    public function unsetDefaultBilling(): void
    {
        if (!$this->isDefaultBilling) {
            return;
        }
        $this->isDefaultBilling = false;
        $this->markAsUpdated();
    }

    /**
     * PlaceOrderHandler bu metodu çağırır; sipariş JSONB kolonlarına
     * yazılan immutable kopya. addressId referans olarak korunur (FK
     * silinince null'a düşer; snapshot her hâlükârda geçerli).
     *
     * snapshotAt: hangi anda alındığı — adres sonradan güncellense
     * "bu siparişin adresi 3 ay önce neydi" sorusu cevaplanabilsin.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'addressId' => $this->id->value(),
            'title' => $this->title,
            'fullName' => $this->fullName,
            'phone' => $this->phone->value(),
            'countryCode' => $this->countryCode,
            'city' => $this->city,
            'district' => $this->district,
            'neighborhood' => $this->neighborhood,
            'postalCode' => $this->postalCode,
            'addressLine' => $this->addressLine,
            'addressLine2' => $this->addressLine2,
            'snapshotAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'userId' => $this->userId->value(),
            'title' => $this->title,
            'fullName' => $this->fullName,
            'phone' => $this->phone->value(),
            'countryCode' => $this->countryCode,
            'city' => $this->city,
            'district' => $this->district,
            'neighborhood' => $this->neighborhood,
            'postalCode' => $this->postalCode,
            'addressLine' => $this->addressLine,
            'addressLine2' => $this->addressLine2,
            'isDefaultDelivery' => $this->isDefaultDelivery,
            'isDefaultBilling' => $this->isDefaultBilling,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: AddressId::fromString((string) $data['id']),
            userId: UserId::fromString((string) $data['userId']),
            title: (string) $data['title'],
            fullName: (string) $data['fullName'],
            phone: PhoneNumber::fromString((string) $data['phone']),
            countryCode: (string) ($data['countryCode'] ?? self::COUNTRY_TR),
            city: (string) $data['city'],
            district: (string) $data['district'],
            neighborhood: isset($data['neighborhood']) && $data['neighborhood'] !== null
                ? (string) $data['neighborhood']
                : null,
            postalCode: isset($data['postalCode']) && $data['postalCode'] !== null
                ? (string) $data['postalCode']
                : null,
            addressLine: (string) $data['addressLine'],
            addressLine2: isset($data['addressLine2']) && $data['addressLine2'] !== null
                ? (string) $data['addressLine2']
                : null,
            isDefaultDelivery: (bool) ($data['isDefaultDelivery'] ?? false),
            isDefaultBilling: (bool) ($data['isDefaultBilling'] ?? false),
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) && $data['updatedAt'] !== null
                ? self::parseTimestamp($data['updatedAt'])
                : null,
        );
    }
}
