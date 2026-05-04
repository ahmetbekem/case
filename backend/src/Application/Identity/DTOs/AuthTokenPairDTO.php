<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;

/**
 * Refresh response — fresh access + refresh tokens, no user payload
 * (the caller already knows who they are; if they want the user record,
 * they call /me).
 */
final readonly class AuthTokenPairDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $accessToken,
        public int $accessTokenExpiresIn,
        public string $refreshToken,
        public string $tokenType = 'Bearer',
    ) {
    }
}
