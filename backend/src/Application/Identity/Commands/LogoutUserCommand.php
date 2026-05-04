<?php

declare(strict_types=1);

namespace Application\Identity\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class LogoutUserCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $refreshToken,
        // Optional: when present we also blacklist the access token jti
        // for the rest of its lifetime so it can't be replayed before its
        // natural 15-minute expiry. Pulled from JwtAuthMiddleware attributes
        // by AuthController::logout (request->attributes 'jwt_jti' / 'jwt_exp').
        public ?string $accessTokenJti = null,
        public ?int $accessTokenRemainingTtl = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'LogoutUser';
    }
}
