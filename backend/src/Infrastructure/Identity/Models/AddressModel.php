<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Infrastructure\Shared\Persistence\BaseModel;

final class AddressModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'identity.addresses';

    protected $guarded = [];

    protected $casts = [
        'is_default_delivery' => 'boolean',
        'is_default_billing' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
