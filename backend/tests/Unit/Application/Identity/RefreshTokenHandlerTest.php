<?php

declare(strict_types=1);

use Application\Identity\Commands\RefreshTokenCommand;
use Application\Identity\DTOs\AuthTokenPairDTO;
use Application\Identity\Handlers\Commands\RefreshTokenHandler;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\Entities\User;
use Domain\Identity\Enums\UserRole;
use Domain\Identity\Exceptions\InvalidRefreshTokenException;
use Domain\Identity\Exceptions\RefreshTokenExpiredException;
use Domain\Identity\Exceptions\RefreshTokenReplayException;
use Domain\Identity\ValueObjects\Email;
use Domain\Identity\ValueObjects\HashedPassword;
use Domain\Identity\ValueObjects\IssuedJwt;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
    $this->tokenRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $this->jwtIssuer = Mockery::mock(JwtIssuerInterface::class);
    $this->auditLogger = Mockery::mock(AuditLoggerInterface::class);
    $this->transaction = Mockery::mock(TransactionInterface::class);
    $this->transaction->shouldReceive('run')->andReturnUsing(fn ($cb) => $cb());

    $this->handler = new RefreshTokenHandler(
        $this->userRepo,
        $this->tokenRepo,
        $this->jwtIssuer,
        $this->auditLogger,
        $this->transaction,
        new NullLogger(),
    );
});

function makeUser(): User
{
    return new User(
        id: UserId::generate(),
        email: Email::fromString('u@test.local'),
        passwordHash: HashedPassword::fromString('$2y$10$' . str_repeat('a', 53)),
        fullName: 'U',
        role: UserRole::CUSTOMER,
    );
}

function makeRefreshCommand(string $plaintext, string $userId = 'da4a9895-cf0e-4732-89e9-915c8ae96f02'): RefreshTokenCommand
{
    return new RefreshTokenCommand(
        userId: $userId,
        correlationId: 'corr-test',
        userIp: '127.0.0.1',
        refreshToken: $plaintext,
        userAgent: 'phpunit',
    );
}

it('rejects a missing refresh token', function () {
    $this->handler->handle(makeRefreshCommand(''));
})->throws(InvalidRefreshTokenException::class);

it('rejects an unknown refresh token', function () {
    $this->tokenRepo->shouldReceive('findByTokenHash')->andReturn(null);

    $this->handler->handle(makeRefreshCommand(str_repeat('a', 64)));
})->throws(InvalidRefreshTokenException::class);

it('rejects an expired refresh token', function () {
    // expiresAt is readonly — construct directly with a past timestamp
    // instead of trying to mutate a generated entity via reflection.
    $expired = new RefreshToken(
        id: \Domain\Identity\ValueObjects\RefreshTokenId::generate(),
        userId: UserId::generate(),
        tokenHash: hash('sha256', 'plaintext'),
        familyId: '11111111-2222-3333-4444-555555555555',
        expiresAt: new DateTimeImmutable('-1 second'),
    );

    $this->tokenRepo->shouldReceive('findByTokenHash')->andReturn($expired);

    $this->handler->handle(makeRefreshCommand(str_repeat('b', 64)));
})->throws(RefreshTokenExpiredException::class);

it('rotates a valid token: saves new, marks old as rotated, returns DTO', function () {
    $user = makeUser();
    $generated = RefreshToken::generate($user->getId());
    $oldToken = $generated['token'];

    $this->tokenRepo->shouldReceive('findByTokenHash')->andReturn($oldToken);
    $this->userRepo->shouldReceive('findById')->andReturn($user);
    $this->jwtIssuer->shouldReceive('issue')->with($user)->andReturn(
        new IssuedJwt('jwt-string', 'jti-123', new DateTimeImmutable('+15 minutes')),
    );
    // Order matters: NEW token saved first (FK on old.replaced_by_token_id)
    $saveOrder = [];
    $this->tokenRepo->shouldReceive('save')
        ->twice()
        ->andReturnUsing(function ($t) use (&$saveOrder, $oldToken) {
            $saveOrder[] = $t === $oldToken ? 'old' : 'new';
        });
    $this->auditLogger->shouldReceive('log')->once();

    $result = $this->handler->handle(makeRefreshCommand($generated['plaintext']));

    expect($result)->toBeInstanceOf(AuthTokenPairDTO::class)
        ->and($result->accessToken)->toBe('jwt-string')
        ->and($result->refreshToken)->toMatch('/^[a-f0-9]{64}$/')
        ->and($saveOrder)->toBe(['new', 'old']);
});

it('detects replay: revokes the family + emits critical audit + throws', function () {
    $user = makeUser();
    $rotated = RefreshToken::generate($user->getId())['token'];
    $rotated->markAsRotated(\Domain\Identity\ValueObjects\RefreshTokenId::generate());
    // Force past grace window so isRevoked() returns true
    (new ReflectionProperty($rotated, 'revokedAt'))->setValue(
        $rotated,
        new DateTimeImmutable('-' . (RefreshToken::ROTATION_GRACE_SECONDS + 1) . ' seconds'),
    );

    $this->tokenRepo->shouldReceive('findByTokenHash')->andReturn($rotated);
    $this->tokenRepo->shouldReceive('revokeFamily')
        ->once()
        ->with($rotated->getFamilyId(), RefreshToken::REVOKE_REASON_REPLAY);
    $this->auditLogger->shouldReceive('log')
        ->once()
        ->withArgs(fn ($event) => $event->action === 'security.refresh_token_replay');

    $this->handler->handle(makeRefreshCommand(str_repeat('c', 64)));
})->throws(RefreshTokenReplayException::class);

it('rejects refresh when owner is missing or inactive', function () {
    $valid = RefreshToken::generate(UserId::generate())['token'];

    $this->tokenRepo->shouldReceive('findByTokenHash')->andReturn($valid);
    $this->userRepo->shouldReceive('findById')->andReturn(null);

    $this->handler->handle(makeRefreshCommand(str_repeat('d', 64)));
})->throws(InvalidRefreshTokenException::class);
