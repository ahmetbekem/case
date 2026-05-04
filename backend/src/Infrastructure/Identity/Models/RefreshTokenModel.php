<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Models;

use Infrastructure\Shared\Persistence\BaseModel;

/**
 * Refresh tokens are NOT soft-deleted — revocation is tracked via the
 * `revoked_at` column directly so we keep the rotation chain auditable
 * even after the cleanup job removes long-expired rows.
 */
final class RefreshTokenModel extends BaseModel
{
    protected $table = 'identity.refresh_tokens';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
