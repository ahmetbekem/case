<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\LogoutUserCommand;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\JwtBlacklistInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Entities\RefreshToken;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

final class LogoutUserHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly JwtBlacklistInterface $jwtBlacklist,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return LogoutUserCommand::class;
    }

    protected function doHandle(CommandInterface $command): mixed
    {
        assert($command instanceof LogoutUserCommand);

        // No refresh token in the cookie — likely already logged out. No-op.
        if ($command->refreshToken === '') {
            return null;
        }

        $tokenHash = hash('sha256', $command->refreshToken);
        $token = $this->refreshTokenRepository->findByTokenHash($tokenHash);

        // Unknown token — silent no-op (do not leak which tokens exist)
        if ($token === null) {
            return null;
        }

        // Revoke the entire family — kills every refresh token issued from
        // this login chain, so concurrent sessions/tabs are also logged out
        $this->refreshTokenRepository->revokeFamily(
            familyId: $token->getFamilyId(),
            reason: RefreshToken::REVOKE_REASON_LOGOUT,
        );

        // Blacklist the still-valid access token for the rest of its lifetime
        // so it cannot be reused before its natural expiry (SECURITY.md §4.6).
        if ($command->accessTokenJti !== null && $command->accessTokenRemainingTtl !== null) {
            $this->jwtBlacklist->add(
                $command->accessTokenJti,
                $command->accessTokenRemainingTtl,
            );
        }

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $token->getUserId()->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.logout',
            resource: 'family:' . $token->getFamilyId(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
        ));

        return null;
    }
}
