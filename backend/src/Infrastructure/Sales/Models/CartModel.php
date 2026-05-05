<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Models;

use Infrastructure\Shared\Persistence\BaseModel;

/**
 * Carts have no soft-delete column — lifecycle is tracked via the
 * `status` field (active / converted / abandoned). Conversion to an
 * order does NOT delete the cart row; analytics keeps the history.
 */
final class CartModel extends BaseModel
{
    protected $table = 'sales.carts';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
