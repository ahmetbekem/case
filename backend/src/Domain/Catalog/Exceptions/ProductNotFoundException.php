<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class ProductNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'Product not found',
            errorCode: 'PRODUCT_NOT_FOUND',
            context: ['productId' => $id],
        );
    }

    public static function withSku(string $sku): self
    {
        return new self(
            message: 'Product not found',
            errorCode: 'PRODUCT_NOT_FOUND',
            context: ['sku' => $sku],
        );
    }
}
