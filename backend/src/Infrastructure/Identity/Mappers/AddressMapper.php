<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Mappers;

use Domain\Identity\Entities\Address;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Identity\Models\AddressModel;

final class AddressMapper extends AbstractMapper
{
    public function toEntity(object $model): Address
    {
        assert($model instanceof AddressModel);

        return Address::fromArray([
            'id' => $model->id,
            'userId' => $model->user_id,
            'title' => $model->title,
            'fullName' => $model->full_name,
            'phone' => $model->phone,
            'countryCode' => $model->country_code,
            'city' => $model->city,
            'district' => $model->district,
            'neighborhood' => $model->neighborhood,
            'postalCode' => $model->postal_code,
            'addressLine' => $model->address_line,
            'addressLine2' => $model->address_line_2,
            'isDefaultDelivery' => (bool) $model->is_default_delivery,
            'isDefaultBilling' => (bool) $model->is_default_billing,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof Address);

        return [
            'id' => $entity->getId()->value(),
            'user_id' => $entity->getUserId()->value(),
            'title' => $entity->getTitle(),
            'full_name' => $entity->getFullName(),
            'phone' => $entity->getPhone()->value(),
            'country_code' => $entity->getCountryCode(),
            'city' => $entity->getCity(),
            'district' => $entity->getDistrict(),
            'neighborhood' => $entity->getNeighborhood(),
            'postal_code' => $entity->getPostalCode(),
            'address_line' => $entity->getAddressLine(),
            'address_line_2' => $entity->getAddressLine2(),
            'is_default_delivery' => $entity->isDefaultDelivery(),
            'is_default_billing' => $entity->isDefaultBilling(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
