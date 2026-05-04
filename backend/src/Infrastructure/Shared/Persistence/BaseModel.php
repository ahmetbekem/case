<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Common Eloquent base for all module models.
 *
 * Conventions enforced here:
 * - String UUID primary keys (matches ADR-011: app-generated UUID v4)
 * - No incrementing keys
 * - Standard timestamps cast to DateTime
 *
 * Concrete models declare $table = 'schema.table_name' (schema-qualified).
 */
abstract class BaseModel extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
