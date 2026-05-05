<?php

declare(strict_types=1);

namespace Domain\Identity\Entities;

use Domain\Identity\Enums\UserRole;
use Domain\Identity\ValueObjects\Email;
use Domain\Identity\ValueObjects\HashedPassword;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;

final class User extends AbstractEntity
{
    public function __construct(
        private readonly UserId $id,
        private Email $email,
        private HashedPassword $passwordHash,
        private string $fullName,
        private UserRole $role = UserRole::CUSTOMER,
        private bool $isActive = true,
        private ?\DateTimeImmutable $emailVerifiedAt = null,
        private ?\DateTimeImmutable $lastLoginAt = null,
        private ?string $lastLoginIp = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPasswordHash(): HashedPassword
    {
        return $this->passwordHash;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->passwordHash = $password;
        $this->markAsUpdated();
    }

    public function rename(string $fullName): void
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            throw new \InvalidArgumentException('Full name cannot be empty');
        }
        $this->fullName = $fullName;
        $this->markAsUpdated();
    }

    public function recordLogin(string $ip): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->lastLoginIp = $ip;
        $this->markAsUpdated();
    }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->markAsUpdated();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->markAsUpdated();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->markAsUpdated();
    }

    public function promoteToAdmin(): void
    {
        $this->role = UserRole::ADMIN;
        $this->markAsUpdated();
    }

    public function demoteToCustomer(): void
    {
        $this->role = UserRole::CUSTOMER;
        $this->markAsUpdated();
    }

    /**
     * Public-facing array form. **Excludes** passwordHash. The Mapper
     * reaches into getPasswordHash() directly when persisting.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'email' => $this->email->value,
            'fullName' => $this->fullName,
            'role' => $this->role->value,
            'isActive' => $this->isActive,
            'emailVerifiedAt' => $this->emailVerifiedAt?->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => $this->lastLoginAt?->format(\DateTimeInterface::ATOM),
            'lastLoginIp' => $this->lastLoginIp,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Reconstruct from persisted form. Mapper-only entry point — accepts
     * passwordHash because the database row carries it.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: UserId::fromString((string) $data['id']),
            email: Email::fromString((string) $data['email']),
            passwordHash: HashedPassword::fromString((string) $data['passwordHash']),
            fullName: (string) $data['fullName'],
            role: UserRole::from((string) $data['role']),
            isActive: (bool) ($data['isActive'] ?? true),
            emailVerifiedAt: isset($data['emailVerifiedAt'])
                ? self::parseTimestamp($data['emailVerifiedAt'])
                : null,
            lastLoginAt: isset($data['lastLoginAt'])
                ? self::parseTimestamp($data['lastLoginAt'])
                : null,
            lastLoginIp: $data['lastLoginIp'] ?? null,
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt'])
                ? self::parseTimestamp($data['updatedAt'])
                : null,
        );
    }
}
