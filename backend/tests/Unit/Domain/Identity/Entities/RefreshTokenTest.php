<?php

declare(strict_types=1);

use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\ValueObjects\RefreshTokenId;
use Domain\Shared\ValueObjects\UserId;

describe('RefreshToken::generate', function () {
    it('returns both the entity and a fresh hex plaintext', function () {
        $userId = UserId::generate();
        $result = RefreshToken::generate($userId);

        expect($result)->toHaveKeys(['token', 'plaintext'])
            ->and($result['token'])->toBeInstanceOf(RefreshToken::class)
            ->and($result['plaintext'])->toMatch('/^[a-f0-9]{64}$/');
    });

    it('stores the SHA-256 hash of the plaintext (never the plaintext itself)', function () {
        $result = RefreshToken::generate(UserId::generate());

        expect($result['token']->getTokenHash())
            ->toBe(hash('sha256', $result['plaintext']));
    });

    it('reuses the provided familyId when continuing a rotation chain', function () {
        $familyId = '11111111-2222-3333-4444-555555555555';
        $result = RefreshToken::generate(UserId::generate(), familyId: $familyId);

        expect($result['token']->getFamilyId())->toBe($familyId);
    });

    it('mints a new familyId when none is supplied', function () {
        $a = RefreshToken::generate(UserId::generate());
        $b = RefreshToken::generate(UserId::generate());

        expect($a['token']->getFamilyId())->not->toBe($b['token']->getFamilyId());
    });
});

describe('RefreshToken state machine', function () {
    it('is not expired when expiresAt is in the future', function () {
        $token = RefreshToken::generate(UserId::generate(), lifetimeDays: 7)['token'];

        expect($token->isExpired())->toBeFalse();
    });

    it('is not revoked freshly minted', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];

        expect($token->isRevoked())->toBeFalse();
    });

    it('markAsRotated stamps revokedAt + replacedByTokenId + reason', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];
        $newId = RefreshTokenId::generate();

        $token->markAsRotated($newId);

        expect($token->getRevokedAt())->not->toBeNull()
            ->and($token->getReplacedByTokenId()?->value())->toBe($newId->value())
            ->and($token->getRevokeReason())->toBe(RefreshToken::REVOKE_REASON_ROTATED);
    });

    it('treats a freshly-rotated token as still active during the grace window', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];
        $token->markAsRotated(RefreshTokenId::generate());

        // Just rotated — within grace window → not yet "revoked" semantically
        expect($token->isRevoked())->toBeFalse();
    });

    it('treats a rotated token as revoked once the grace window has lapsed', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];
        $token->markAsRotated(RefreshTokenId::generate());

        // Force revokedAt back past the grace window via reflection
        $revokedAtProp = new ReflectionProperty($token, 'revokedAt');
        $revokedAtProp->setValue($token, new DateTimeImmutable(
            '-' . (RefreshToken::ROTATION_GRACE_SECONDS + 1) . ' seconds'
        ));

        expect($token->isRevoked())->toBeTrue();
    });

    it('non-rotation revoke reasons are immediately revoked (no grace)', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];

        $token->revoke(RefreshToken::REVOKE_REASON_LOGOUT);

        expect($token->isRevoked())->toBeTrue();
    });

    it('revoke is idempotent — second call preserves the original reason', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];

        $token->revoke(RefreshToken::REVOKE_REASON_LOGOUT);
        $firstRevokedAt = $token->getRevokedAt();
        $token->revoke(RefreshToken::REVOKE_REASON_REPLAY);

        expect($token->getRevokeReason())->toBe(RefreshToken::REVOKE_REASON_LOGOUT)
            ->and($token->getRevokedAt())->toBe($firstRevokedAt);
    });

    it('rejects an unknown revoke reason', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];
        $token->revoke('bogus');
    })->throws(InvalidArgumentException::class, 'Invalid revoke reason');

    it('refuses to rotate a token that is already revoked', function () {
        $token = RefreshToken::generate(UserId::generate())['token'];
        $token->revoke(RefreshToken::REVOKE_REASON_LOGOUT);

        $token->markAsRotated(RefreshTokenId::generate());
    })->throws(LogicException::class, 'already revoked');
});
