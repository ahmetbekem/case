<?php

declare(strict_types=1);

namespace Domain\Identity\Contracts;

use Domain\Identity\Entities\User;
use Domain\Identity\ValueObjects\IssuedJwt;

interface JwtIssuerInterface
{
    /**
     * Issue an access token JWT for the given user. The implementation
     * generates a fresh jti so the caller can later blacklist this exact
     * token without parsing it again.
     */
    public function issue(User $user): IssuedJwt;

    /**
     * Verify the JWT signature + standard claims (iss, aud, exp).
     * Returns the claims array on success, null on any failure
     * (signature mismatch, expired, malformed, wrong algorithm).
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $jwt): ?array;
}
