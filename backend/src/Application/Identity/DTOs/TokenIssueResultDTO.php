<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;

/**
 * Login + Register response — bundles the freshly issued token pair
 * with the user record (so the client can populate the auth store
 * in a single round-trip).
 */
final readonly class TokenIssueResultDTO extends AbstractOutputDTO
{
    public function __construct(
        public UserDTO $user,
        public string $accessToken,
        public int $accessTokenExpiresIn,
        public string $refreshToken,
        public string $tokenType = 'Bearer',
    ) {
    }
}
