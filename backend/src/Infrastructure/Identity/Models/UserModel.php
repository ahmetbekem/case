<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Infrastructure\Shared\Persistence\BaseModel;

final class UserModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'identity.users';

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
