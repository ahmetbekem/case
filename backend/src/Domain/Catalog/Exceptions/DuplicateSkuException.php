<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

final class DuplicateSkuException extends BusinessRuleException
{
    public static function withSku(string $sku): self
    {
        return new self(
            message: 'SKU is already in use',
            errorCode: 'DUPLICATE_SKU',
            context: ['sku' => $sku],
        );
    }
}
