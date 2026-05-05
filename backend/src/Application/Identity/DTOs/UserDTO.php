<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Identity\Entities\User;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserDTO',
    title: 'User',
    description: 'Kullanıcı kaydı — şifre alanı asla dönmez.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'fullName', type: 'string', example: 'Ali Veli'),
        new OA\Property(property: 'role', type: 'string', enum: ['customer', 'admin'], example: 'customer'),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
        new OA\Property(property: 'emailVerifiedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'lastLoginAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class UserDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        public string $role,
        public bool $isActive,
        public ?string $emailVerifiedAt,
        public ?string $lastLoginAt,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId()->value(),
            email: $user->getEmail()->value,
            fullName: $user->getFullName(),
            role: $user->getRole()->value,
            isActive: $user->isActive(),
            emailVerifiedAt: $user->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            lastLoginAt: $user->getLastLoginAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, User> $users
     * @return array<int, self>
     */
    public static function collection(array $users): array
    {
        return array_map(fn (User $u) => self::fromEntity($u), $users);
    }
}
