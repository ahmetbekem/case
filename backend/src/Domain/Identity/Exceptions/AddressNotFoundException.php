<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

/**
 * ADR-018 — Adres bulunamadı (kullanıcının kendisinde de yok). IDOR
 * koruması: başka kullanıcının adresine erişim de aynı errorCode'u
 * döner; saldırgan adres ID'sinin gerçekten var olup olmadığını
 * çıkaramasın.
 */
final class AddressNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'Address not found',
            errorCode: 'ADDRESS_NOT_FOUND',
            context: ['id' => $id],
        );
    }
}
