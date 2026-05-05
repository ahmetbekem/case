<?php

declare(strict_types=1);

namespace Infrastructure\Currency\Models;

use Infrastructure\Shared\Persistence\BaseModel;

/**
 * Exchange rates are immutable historical data — no updated_at column,
 * so Laravel's automatic timestamp touch is disabled. created_at is set
 * explicitly by the mapper at insert time.
 */
final class ExchangeRateModel extends BaseModel
{
    protected $table = 'currency.exchange_rates';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'rate' => 'float',
        'rate_date' => 'date',
        'fetched_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
