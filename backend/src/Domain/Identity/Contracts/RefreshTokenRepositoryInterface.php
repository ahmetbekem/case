<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\ValueObjects\RefreshTokenId;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\UserId;

/**
 * @extends RepositoryInterface<RefreshToken, RefreshTokenId>
 */
interface RefreshTokenRepositoryInterface extends RepositoryInterface
{
    public function findById(RefreshTokenId $id): ?RefreshToken;

    public function findByTokenHash(string $tokenHash): ?RefreshToken;

    public function save(RefreshToken $token): void;

    /**
     * Mark every still-active token in the family as revoked. Returns the
     * number of rows affected. Used on replay detection to log the user
     * out of every device that shares this token chain.
     */
    public function revokeFamily(string $familyId, string $reason): int;

    /**
     * Revoke every active token for the user (logout-everywhere).
     */
    public function revokeAllForUser(UserId $userId, string $reason): int;

    /**
     * Cleanup helper — remove revoked tokens whose expires_at is in the past.
     */
    public function deleteExpiredAndRevokedBefore(\DateTimeImmutable $beforeDate): int;
}
