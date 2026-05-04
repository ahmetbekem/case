<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Commands;

use Application\Identity\Commands\RegisterUserCommand;
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
use Domain\Identity\Entities\User;
use Domain\Identity\Enums\UserRole;
use Domain\Identity\Exceptions\EmailAlreadyExistsException;
use Domain\Identity\ValueObjects\Email;
use Domain\Identity\ValueObjects\PlainPassword;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class RegisterUserHandler extends AbstractCommandHandler
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
        return RegisterUserCommand::class;
    }

    /**
     * @throws EmailAlreadyExistsException
     */
    protected function doHandle(CommandInterface $command): TokenIssueResultDTO
    {
        assert($command instanceof RegisterUserCommand);

        $email = Email::fromString($command->email);

        if ($this->userRepository->existsByEmail($email)) {
            throw EmailAlreadyExistsException::withEmail($email->value);
        }

        $hashedPassword = $this->passwordHasher->hash(
            PlainPassword::fromString($command->password),
        );

        $user = new User(
            id: UserId::generate(),
            email: $email,
            passwordHash: $hashedPassword,
            fullName: $command->fullName,
            role: UserRole::CUSTOMER,
        );
        $user->recordLogin($command->getUserIp());

        $this->userRepository->save($user);

        // Issue tokens so the client is logged in immediately after register
        $accessToken = $this->jwtIssuer->issue($user);
        $refreshResult = RefreshToken::generate(
            userId: $user->getId(),
            userAgent: null,
            ipAddress: $command->getUserIp(),
        );
        $this->refreshTokenRepository->save($refreshResult['token']);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $user->getId()->value(),
            actorIp: $command->getUserIp(),
            action: 'identity.register',
            resource: 'user:' . $user->getId()->value(),
            module: 'identity',
            result: 'success',
            correlationId: $command->getCorrelationId(),
        ));

        return new TokenIssueResultDTO(
            user: UserDTO::fromEntity($user),
            accessToken: $accessToken->jwt,
            accessTokenExpiresIn: $accessToken->expiresInSeconds(),
            refreshToken: $refreshResult['plaintext'],
        );
    }
}
