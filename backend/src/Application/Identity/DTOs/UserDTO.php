<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Identity\Entities\User;

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
