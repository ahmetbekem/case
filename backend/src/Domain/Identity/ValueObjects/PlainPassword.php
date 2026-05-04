<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\ValidationException;

/**
 * The user's plaintext password — only ever lives in memory between
 * the inbound request and the password hasher. Never persisted, never
 * logged. Strict length bounds match SECURITY.md guidance + bcrypt's
 * 72-byte input ceiling.
 */
final readonly class PlainPassword extends AbstractValueObject
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    public function __construct(public string $value)
    {
        $length = strlen($value);

        if ($length < self::MIN_LENGTH) {
            throw ValidationException::withMessage(
                'Password must be at least ' . self::MIN_LENGTH . ' characters',
                ['length' => $length],
            );
        }
        if ($length > self::MAX_LENGTH) {
            throw ValidationException::withMessage(
                'Password exceeds ' . self::MAX_LENGTH . ' characters',
                ['length' => $length],
            );
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
