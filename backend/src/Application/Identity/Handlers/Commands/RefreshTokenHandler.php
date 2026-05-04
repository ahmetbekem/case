<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\RefreshTokenCommand;
use Application\Identity\DTOs\AuthTokenPairDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\Exceptions\InvalidRefreshTokenException;
use Domain\Identity\Exceptions\RefreshTokenExpiredException;
use Domain\Identity\Exceptions\RefreshTokenReplayException;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

/**
 * Refresh-token rotation handler with replay detection.
 *
 * Every successful refresh marks the old token as `rotated` and mints a
 * brand-new token within the SAME family. If a token already in `revoked`
 * state (past the rotation grace window) is presented again, the entire
 * family is killed and a critical-severity audit event is emitted —
 * this is the canonical signal that the original token was stolen
 * (SECURITY.md §4.4).
 */
final class RefreshTokenHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly JwtIssuerInterface $jwtIssuer,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return RefreshTokenCommand::class;
    }

    /**
     * Override AbstractCommandHandler::handle() to bypass the transaction
     * wrapper. Replay detection's revokeFamily UPDATE MUST commit even
     * when we throw RefreshTokenReplayException immediately afterward —
     * if it lived inside a transaction the rollback would un-revoke the
     * stolen family, defeating the whole point of detection.
     *
     * Each individual operation in this handler is already atomic at the
     * SQL level (single UPDATE / INSERT statements), so we lose nothing
     * meaningful by stepping outside the wrapping transaction.
     */
    public function handle(CommandInterface $command): mixed
    {
        $startedAt = microtime(true);
        try {
            $result = $this->doHandle($command);
            $this->logger->info('Command handled', [
                'command' => $command->getCommandName(),
                'durationMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'correlationId' => $command->getCorrelationId(),
                'userId' => $command->getUserId(),
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Command failed', [
                'command' => $command->getCommandName(),
                'errorClass' => $e::class,
                'error' => $e->getMessage(),
                'correlationId' => $command->getCorrelationId(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws InvalidRefreshTokenException
     * @throws RefreshTokenExpiredException
     * @throws RefreshTokenReplayException
     */
    protected function doHandle(CommandInterface $command): AuthTokenPairDTO
    {
        assert($command instanceof RefreshTokenCommand);

        if ($command->refreshToken === '') {
            throw InvalidRefreshTokenException::missing();
        }

        $tokenHash = hash('sha256', $command->refreshToken);
        $token = $this->refreshTokenRepository->findByTokenHash($tokenHash);

        if ($token === null) {
            throw InvalidRefreshTokenException::notFound();
        }

        // ⚠️ REPLAY DETECTION — most critical security path in this module.
        // A token whose isRevoked() is true (past grace) being presented again
        // is overwhelmingly likely to be theft, not a legitimate retry.
        if ($token->isRevoked()) {
            $this->refreshTokenRepository->revokeFamily(
                familyId: $token->getFamilyId(),
                reason: RefreshToken::REVOKE_REASON_REPLAY,
            );

            $this->auditLogger->log(new AuditEvent(
                actor: 'userId:' . $token->getUserId()->value(),
                actorIp: $command->getUserIp(),
                action: 'security.refresh_token_replay',
                resource: 'family:' . $token->getFamilyId(),
                module: 'identity',
                result: 'failure',
                correlationId: $command->getCorrelationId(),
                userAgent: $command->userAgent,
                metadata: [
                    'tokenId' => $token->getId()->value(),
                    'originalRevokeReason' => $token->getRevokeReason(),
                ],
            ));

            throw RefreshTokenReplayException::detected(
                $token->getFamilyId(),
                $token->getUserId()->value(),
            );
        }

        if ($token->isExpired()) {
            throw RefreshTokenExpiredException::withId($token->getId()->value());
        }

        // Owner must still exist and be active
        $user = $this->userRepository->findById($token->getUserId());
        if ($user === null || !$user->isActive()) {
            throw InvalidRefreshTokenException::notFound();
        }

        // ✅ Valid — mint new tokens within the SAME family
        $newAccessToken = $this->jwtIssuer->issue($user);
        $newRefreshResult = RefreshToken::generate(
            userId: $user->getId(),
            familyId: $token->getFamilyId(),
            userAgent: $command->userAgent,
            ipAddress: $command->getUserIp(),
        );

        // Insert NEW token first so the FK on the old token's
        // replaced_by_token_id has a target by the time we update it.
        $this->refreshTokenRepository->save($newRefreshResult['token']);
        $token->markAsRotated($newRefreshResult['token']->getId());
        $this->refreshTokenRepository->save($token);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $user->getId()->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.refresh_token',
            resource: 'family:' . $token->getFamilyId(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            userAgent: $command->userAgent,
        ));

        return new AuthTokenPairDTO(
            accessToken: $newAccessToken->jwt,
            accessTokenExpiresIn: $newAccessToken->expiresInSeconds(),
            refreshToken: $newRefreshResult['plaintext'],
        );
    }
}
