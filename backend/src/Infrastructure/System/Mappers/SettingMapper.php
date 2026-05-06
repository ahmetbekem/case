<?php

declare(strict_types=1);

namespace Infrastructure\System\Mappers;

use Domain\System\Entities\Setting;
use Infrastructure\System\Models\SettingModel;

/**
 * Setting AbstractEntity'den extend etmediği için SettingMapper de
 * AbstractMapper'i extend etmez — toEntity dönüş tipi EntityInterface
 * olamaz. Aynı convention'i sade tutuyoruz: toEntity + toPersistence.
 */
final class SettingMapper
{
    public function toEntity(object $model): Setting
    {
        assert($model instanceof SettingModel);

        return new Setting(
            id: $model->id,
            key: $model->key,
            value: $model->value,
            type: $model->type,
            category: $model->category,
            description: $model->description,
            isPublic: (bool) $model->is_public,
            createdAt: \DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: $model->updated_at !== null
                ? \DateTimeImmutable::createFromInterface($model->updated_at)
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof Setting);

        return [
            'id' => $entity->getId(),
            'key' => $entity->getKey(),
            'value' => $entity->getValue(),
            'type' => $entity->getType(),
            'category' => $entity->getCategory(),
            'description' => $entity->getDescription(),
            'is_public' => $entity->isPublic(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
