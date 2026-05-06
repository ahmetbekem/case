<?php

declare(strict_types=1);

namespace Domain\System\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class SettingNotFoundException extends NotFoundException
{
    public static function withKey(string $key): self
    {
        return new self(
            message: "Setting not found: {$key}",
            errorCode: 'SETTING_NOT_FOUND',
            context: ['key' => $key],
        );
    }
}
