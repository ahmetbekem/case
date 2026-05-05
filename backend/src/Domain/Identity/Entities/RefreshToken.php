<?php

declare(strict_types=1);

namespace Domain\Identity\Entities;

use Domain\Identity\ValueObjects\RefreshTokenId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Ramsey\Uuid\Uuid;

/**
 * Refresh token aggregate.
 *
 * A refresh token belongs to a `family` — every successful rotation issues
 * a new token in the same family while marking the old one as revoked. If
 * a revoked token in the family is presented again past the rotation grace
 * window, the entire family is revoked (replay detection — SECURITY.md §4.4).
 */
final class RefreshToken extends AbstractEntity
{
    public const string REVOKE_REASON_LOGOUT = 'logout';
    public const string REVOKE_REASON_ROTATED = 'rotated';
    public const string REVOKE_REASON_REPLAY = 'replay_detected';
    public const string REVOKE_REASON_ADMIN = 'admin_revoke';
    public const string REVOKE_REASON_EXPIRED = 'expired';

    /** Concurrent-refresh grace: a rotated token presented within this many seconds is still accepted (mobile multi-tab race). */
    public const int ROTATION_GRACE_SECONDS = 5;

    public function __construct(
        private readonly RefreshTokenId $id,
        private readonly UserId $userId,
        private readonly string $tokenHash,
        private readonly string $familyId,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $revokedAt = null,
        private ?RefreshTokenId $replacedByTokenId = null,
        private ?string $revokeReason = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $ipAddress = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Mint a fresh refresh token. Returns the entity AND the plaintext —
     * the database stores only the SHA-256 hash, so the caller must give
     * the plaintext to the cookie before forgetting it.
     *
     * Pass an existing $familyId to continue a rotation chain; pass null
     * (the default) for a brand-new login session.
     *
     * @return array{token: self, plaintext: string}
     */
    public static function generate(
        UserId $userId,
        ?string $familyId = null,
        ?string $userAgent = null,
        ?string $ipAddress = null,
        int $lifetimeDays = 7,
    ): array {
        $plaintext = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plaintext);

        $token = new self(
            id: RefreshTokenId::generate(),
            userId: $userId,
            tokenHash: $hash,
            familyId: $familyId ?? Uuid::uuid4()->toString(),
            expiresAt: (new \DateTimeImmutable())->modify("+{$lifetimeDays} days"),
            userAgent: $userAgent,
            ipAddress: $ipAddress,
        );

        return ['token' => $token, 'plaintext' => $plaintext];
    }

    public function getId(): RefreshTokenId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getFamilyId(): string
    {
        return $this->familyId;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getReplacedByTokenId(): ?RefreshTokenId
    {
        return $this->replacedByTokenId;
    }

    public function getRevokeReason(): ?string
    {
        return $this->revokeReason;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * A token is "revoked" if its revokedAt has passed AND either the
     * revoke reason is non-rotation, or the rotation grace period has lapsed.
     * Within the grace window a `rotated` token is still considered active to
     * absorb concurrent-refresh races (mobile multi-tab).
     */
    public function isRevoked(): bool
    {
        if ($this->revokedAt === null) {
            return false;
        }

        if ($this->revokeReason === self::REVOKE_REASON_ROTATED) {
            $secondsSinceRevoke = time() - $this->revokedAt->getTimestamp();
            return $secondsSinceRevoke > self::ROTATION_GRACE_SECONDS;
        }

        return true;
    }

    public function markAsRotated(RefreshTokenId $newTokenId): void
    {
        if ($this->revokedAt !== null) {
            throw new \LogicException('Cannot rotate a token that is already revoked');
        }
        $this->revokedAt = new \DateTimeImmutable();
        $this->replacedByTokenId = $newTokenId;
        $this->revokeReason = self::REVOKE_REASON_ROTATED;
        $this->markAsUpdated();
    }

    public function revoke(string $reason): void
    {
        if (!in_array($reason, [
            self::REVOKE_REASON_LOGOUT,
            self::REVOKE_REASON_REPLAY,
            self::REVOKE_REASON_ADMIN,
            self::REVOKE_REASON_EXPIRED,
        ], true)) {
            throw new \InvalidArgumentException("Invalid revoke reason: {$reason}");
        }
        if ($this->revokedAt !== null) {
            // Idempotent — already revoked, leave the original reason intact
            return;
        }
        $this->revokedAt = new \DateTimeImmutable();
        $this->revokeReason = $reason;
        $this->markAsUpdated();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'userId' => $this->userId->value(),
            'tokenHash' => $this->tokenHash,
            'familyId' => $this->familyId,
            'expiresAt' => $this->expiresAt->format(\DateTimeInterface::ATOM),
            'revokedAt' => $this->revokedAt?->format(\DateTimeInterface::ATOM),
            'replacedByTokenId' => $this->replacedByTokenId?->value(),
            'revokeReason' => $this->revokeReason,
            'userAgent' => $this->userAgent,
            'ipAddress' => $this->ipAddress,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: RefreshTokenId::fromString((string) $data['id']),
            userId: UserId::fromString((string) $data['userId']),
            tokenHash: (string) $data['tokenHash'],
            familyId: (string) $data['familyId'],
            expiresAt: self::parseTimestamp($data['expiresAt']),
            revokedAt: isset($data['revokedAt'])
                ? self::parseTimestamp($data['revokedAt'])
                : null,
            replacedByTokenId: isset($data['replacedByTokenId'])
                ? RefreshTokenId::fromString((string) $data['replacedByTokenId'])
                : null,
            revokeReason: $data['revokeReason'] ?? null,
            userAgent: $data['userAgent'] ?? null,
            ipAddress: $data['ipAddress'] ?? null,
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt'])
                ? self::parseTimestamp($data['updatedAt'])
                : null,
        );
    }
}
