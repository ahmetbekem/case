<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Mappers;

use Domain\Identity\Entities\User;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Identity\Models\UserModel;

final class UserMapper extends AbstractMapper
{
    public function toEntity(object $model): User
    {
        assert($model instanceof UserModel);

        return User::fromArray([
            'id' => $model->id,
            'email' => $model->email,
            'passwordHash' => $model->password_hash,
            'fullName' => $model->full_name,
            'role' => $model->role,
            'isActive' => (bool) $model->is_active,
            'emailVerifiedAt' => $model->email_verified_at?->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => $model->last_login_at?->format(\DateTimeInterface::ATOM),
            'lastLoginIp' => $model->last_login_ip,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof User);

        return [
            'id' => $entity->getId()->value(),
            'email' => $entity->getEmail()->value,
            'password_hash' => $entity->getPasswordHash()->value,
            'full_name' => $entity->getFullName(),
            'role' => $entity->getRole()->value,
            'is_active' => $entity->isActive(),
            'email_verified_at' => $entity->getEmailVerifiedAt(),
            'last_login_at' => $entity->getLastLoginAt(),
            'last_login_ip' => $entity->getLastLoginIp(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
