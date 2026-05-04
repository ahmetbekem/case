<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\LoginUserCommand;
use Application\Identity\DTOs\TokenIssueResultDTO;
use Application\Identity\DTOs\UserDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Contracts\PasswordHasherInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\Exceptions\InvalidCredentialsException;
use Domain\Identity\ValueObjects\Email;
use Domain\Identity\ValueObjects\PlainPassword;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

final class LoginUserHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly JwtIssuerInterface $jwtIssuer,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return LoginUserCommand::class;
    }

    /**
     * @throws InvalidCredentialsException
     */
    protected function doHandle(CommandInterface $command): TokenIssueResultDTO
    {
        assert($command instanceof LoginUserCommand);

        $email = Email::fromString($command->email);
        $user = $this->userRepository->findByEmail($email);

        // Generic failure — never reveal whether the email exists (user enumeration)
        if ($user === null) {
            $this->emitFailureAudit($command, 'anonymous', 'email:' . $email->value, 'user_not_found');
            throw InvalidCredentialsException::forLogin();
        }

        // Wrap PlainPassword construction — a too-short input must surface
        // as InvalidCredentials (401) not Validation (422), otherwise the
        // 422 reveals "this account exists, you just got the password wrong".
        try {
            $plain = PlainPassword::fromString($command->password);
        } catch (\Domain\Shared\Exceptions\ValidationException) {
            $this->emitFailureAudit(
                $command,
                'userId:' . $user->getId()->value(),
                'user:' . $user->getId()->value(),
                'password_format_invalid',
            );
            throw InvalidCredentialsException::forLogin();
        }

        if (!$this->passwordHasher->verify($plain, $user->getPasswordHash())) {
            $this->emitFailureAudit(
                $command,
                'userId:' . $user->getId()->value(),
                'user:' . $user->getId()->value(),
                'wrong_password',
            );
            throw InvalidCredentialsException::forLogin();
        }

        if (!$user->isActive()) {
            $this->emitFailureAudit(
                $command,
                'userId:' . $user->getId()->value(),
                'user:' . $user->getId()->value(),
                'account_inactive',
            );
            throw InvalidCredentialsException::forInactiveAccount();
        }

        // ✅ Authenticated — issue access + refresh tokens
        $accessToken = $this->jwtIssuer->issue($user);

        $refreshResult = RefreshToken::generate(
            userId: $user->getId(),
            userAgent: $command->userAgent,
            ipAddress: $command->getUserIp(),
        );
        $this->refreshTokenRepository->save($refreshResult['token']);

        // Stamp last_login_at + last_login_ip
        $user->recordLogin($command->getUserIp());
        $this->userRepository->save($user);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $user->getId()->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.login',
            resource: 'user:' . $user->getId()->value(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            userAgent: $command->userAgent,
        ));

        return new TokenIssueResultDTO(
            user: UserDTO::fromEntity($user),
            accessToken: $accessToken->jwt,
            accessTokenExpiresIn: $accessToken->expiresInSeconds(),
            refreshToken: $refreshResult['plaintext'],
        );
    }

    private function emitFailureAudit(
        LoginUserCommand $command,
        string $actor,
        string $resource,
        string $reason,
    ): void {
        $this->auditLogger->log(new AuditEvent(
            actor: $actor,
            actorIp: $command->getUserIp(),
            action: 'identity.login',
            resource: $resource,
            module: 'identity',
            result: 'failure',
            correlationId: $command->getCorrelationId(),
            userAgent: $command->userAgent,
            metadata: ['reason' => $reason],
        ));
    }
}
