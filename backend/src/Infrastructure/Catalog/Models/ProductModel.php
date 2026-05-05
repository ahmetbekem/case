<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Infrastructure\Shared\Persistence\BaseModel;

final class ProductModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'catalog.products';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
