<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\ValidationException;

final readonly class Email extends AbstractValueObject
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw ValidationException::withMessage('Email cannot be empty');
        }
        if (strlen($value) > 320) {
            throw ValidationException::withMessage(
                'Email exceeds 320 characters (RFC 5321)',
                ['length' => strlen($value)],
            );
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage(
                'Invalid email format',
                ['email' => $value],
            );
        }

        // Persisted lowercase — matches identity.users CHECK (email = LOWER(email))
        $this->value = strtolower($value);
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
        return $other instanceof self && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
