<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Models;

use Infrastructure\Shared\Persistence\BaseModel;

final class CartItemModel extends BaseModel
{
    protected $table = 'sales.cart_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
