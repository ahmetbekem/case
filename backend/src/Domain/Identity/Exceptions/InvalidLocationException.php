<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Exceptions\ValidationException;

/**
 * ADR-018 — TurkeyLocationsService doğrulamasında il/ilçe bulunamadı.
 * 422 döner (form validation hatası kategorisinde).
 */
final class InvalidLocationException extends ValidationException
{
    public static function invalidCity(string $city): self
    {
        return new self(
            message: "'{$city}' is not a valid Turkish province",
            errorCode: 'INVALID_CITY',
            context: ['city' => $city],
        );
    }

    public static function invalidDistrict(string $city, string $district): self
    {
        return new self(
            message: "'{$district}' is not a valid district of '{$city}'",
            errorCode: 'INVALID_DISTRICT',
            context: ['city' => $city, 'district' => $district],
        );
    }
}
