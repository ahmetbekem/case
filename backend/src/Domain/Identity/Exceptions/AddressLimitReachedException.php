<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

/**
 * ADR-018 — Bir kullanıcı en fazla 10 aktif adres tutabilir
 * (storage/UI sınırı; soft-delete edilenler sayılmaz).
 */
final class AddressLimitReachedException extends BusinessRuleException
{
    public static function maxReached(int $max): self
    {
        return new self(
            message: "You can have at most {$max} active addresses",
            errorCode: 'ADDRESS_LIMIT_REACHED',
            context: ['maxAddresses' => $max],
        );
    }
}
