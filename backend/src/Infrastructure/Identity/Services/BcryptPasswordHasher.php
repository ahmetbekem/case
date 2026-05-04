<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Services;

use Domain\Identity\Contracts\PasswordHasherInterface;
use Domain\Identity\ValueObjects\HashedPassword;
use Domain\Identity\ValueObjects\PlainPassword;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private readonly int $cost = 12)
    {
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException("Bcrypt cost must be in [4, 31], got {$cost}");
        }
    }

    public function hash(PlainPassword $plain): HashedPassword
    {
        $hash = password_hash($plain->value, PASSWORD_BCRYPT, ['cost' => $this->cost]);
        return new HashedPassword($hash);
    }

    public function verify(PlainPassword $plain, HashedPassword $hashed): bool
    {
        return password_verify($plain->value, $hashed->value);
    }

    public function needsRehash(HashedPassword $hashed): bool
    {
        return password_needs_rehash($hashed->value, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
}
