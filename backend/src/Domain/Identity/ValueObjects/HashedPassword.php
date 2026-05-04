<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\ValidationException;

/**
 * A bcrypt-hashed password ready for persistence. Format: $2[ayb]$<cost>$<salt+hash>.
 * Construction validates the format so we cannot accidentally store a
 * plaintext or wrongly-hashed string in the database.
 */
final readonly class HashedPassword extends AbstractValueObject
{
    public function __construct(public string $value)
    {
        if (strlen($value) > 255) {
            throw ValidationException::withMessage(
                'Hashed password exceeds 255 characters',
                ['length' => strlen($value)],
            );
        }
        if (!preg_match('/^\$2[ayb]\$\d{2}\$.{53}$/', $value)) {
            throw ValidationException::withMessage('Invalid bcrypt hash format');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof self && hash_equals($this->value, $other->value);
    }
}
