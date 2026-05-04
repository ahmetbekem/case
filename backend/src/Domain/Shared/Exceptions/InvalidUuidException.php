<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

final class InvalidUuidException extends ValidationException
{
    public static function forValue(string $value): self
    {
        return new self(
            message: "Invalid UUID: {$value}",
            errorCode: 'INVALID_UUID',
            context: ['value' => $value],
        );
    }
}
