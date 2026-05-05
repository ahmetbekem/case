<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Models;

use Infrastructure\Shared\Persistence\BaseModel;

final class OrderModel extends BaseModel
{
    protected $table = 'sales.orders';

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'display_total' => 'decimal:2',
        'exchange_rate_snapshot' => 'decimal:8',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
