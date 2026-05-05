<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Models;

use Infrastructure\Shared\Persistence\BaseModel;

/**
 * Immutable line item — no `updated_at` column (DATABASE_SCHEMA §5.4),
 * so Laravel's automatic timestamp touch is disabled.
 */
final class OrderItemModel extends BaseModel
{
    protected $table = 'sales.order_items';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
