<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

/**
 * Result of a successful JWT issuance — the wire-form token plus the
 * structured metadata callers need without re-decoding (jti for blacklist,
 * expiresAt for cookie maxAge).
 *
 * Lives on the Domain side because it is the contractual return type
 * of JwtIssuerInterface; framework-agnostic plain data.
 */
final readonly class IssuedJwt
{
    public function __construct(
        public string $jwt,
        public string $jti,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public function expiresInSeconds(): int
    {
        return max(0, $this->expiresAt->getTimestamp() - time());
    }
}
