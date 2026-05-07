<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Identity\Entities\Address;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AddressDTO',
    title: 'Address',
    description: 'Kullanıcı adres rehberindeki bir kayıt (ADR-018).',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'Ev'),
        new OA\Property(property: 'fullName', type: 'string', example: 'Ahmet Bekem'),
        new OA\Property(property: 'phone', type: 'string', example: '+905551234567'),
        new OA\Property(property: 'phoneDisplay', type: 'string', example: '0 555 123 45 67'),
        new OA\Property(property: 'countryCode', type: 'string', example: 'TR'),
        new OA\Property(property: 'city', type: 'string', example: 'İstanbul'),
        new OA\Property(property: 'district', type: 'string', example: 'Kadıköy'),
        new OA\Property(property: 'neighborhood', type: 'string', nullable: true, example: 'Caferağa'),
        new OA\Property(property: 'postalCode', type: 'string', nullable: true, example: '34710'),
        new OA\Property(property: 'addressLine', type: 'string', example: 'Moda Cad. No: 123 D: 5'),
        new OA\Property(property: 'addressLine2', type: 'string', nullable: true),
        new OA\Property(property: 'isDefaultDelivery', type: 'boolean'),
        new OA\Property(property: 'isDefaultBilling', type: 'boolean'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class AddressDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $fullName,
        public string $phone,
        public string $phoneDisplay,
        public string $countryCode,
        public string $city,
        public string $district,
        public ?string $neighborhood,
        public ?string $postalCode,
        public string $addressLine,
        public ?string $addressLine2,
        public bool $isDefaultDelivery,
        public bool $isDefaultBilling,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Address $address): self
    {
        return new self(
            id: $address->getId()->value(),
            title: $address->getTitle(),
            fullName: $address->getFullName(),
            phone: $address->getPhone()->value(),
            phoneDisplay: $address->getPhone()->toDisplay(),
            countryCode: $address->getCountryCode(),
            city: $address->getCity(),
            district: $address->getDistrict(),
            neighborhood: $address->getNeighborhood(),
            postalCode: $address->getPostalCode(),
            addressLine: $address->getAddressLine(),
            addressLine2: $address->getAddressLine2(),
            isDefaultDelivery: $address->isDefaultDelivery(),
            isDefaultBilling: $address->isDefaultBilling(),
            createdAt: $address->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $address->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, Address> $addresses
     * @return array<int, self>
     */
    public static function collection(array $addresses): array
    {
        return array_map(static fn (Address $a) => self::fromEntity($a), $addresses);
    }
}
