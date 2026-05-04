<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Services;

use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Entities\User;
use Domain\Identity\ValueObjects\IssuedJwt;
use Ramsey\Uuid\Uuid;

/**
 * Self-contained HS256 JWT issuer.
 *
 * Why not the tymon/jwt-auth runtime? It is geared around Laravel's auth
 * guard and a Model-bound JWTSubject contract — neither of which fits our
 * Onion architecture cleanly. The composer dep is still installed because
 * its sibling packages (php-open-source-saver/jwt-auth ecosystem) may be
 * useful when we move to RS256.
 *
 * Spec compliance:
 * - HS256 only — verify() rejects any other `alg` value (algorithm
 *   confusion guard, SECURITY.md §4.3 forbidden list)
 * - Required claims: iss, aud, sub, iat, exp, jti
 * - Custom claim: role
 *
 * RS256 swap path (TASK 0.7 follow-up): replace hash_hmac signer with
 * openssl_sign / openssl_verify; same interface so no caller change.
 */
final class HmacJwtIssuer implements JwtIssuerInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttlMinutes,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException(
                'JWT secret must be at least 32 characters (got ' . strlen($secret) . ')',
            );
        }
        if ($ttlMinutes < 1) {
            throw new \InvalidArgumentException("TTL must be positive minutes, got {$ttlMinutes}");
        }
    }

    public function issue(User $user): IssuedJwt
    {
        $now = time();
        $exp = $now + ($this->ttlMinutes * 60);
        $jti = Uuid::uuid4()->toString();

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => $user->getId()->value(),
            'iat' => $now,
            'exp' => $exp,
            'jti' => $jti,
            'role' => $user->getRole()->value,
        ];

        $jwt = $this->encodeToken(['typ' => 'JWT', 'alg' => 'HS256'], $payload);

        return new IssuedJwt(
            jwt: $jwt,
            jti: $jti,
            expiresAt: new \DateTimeImmutable("@{$exp}"),
        );
    }

    public function verify(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = $this->base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);

        // Algorithm confusion guard — refuse 'none' and any non-HS256 alg
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expectedSignature = $this->signature($headerB64 . '.' . $payloadB64);
        $providedSignature = $this->base64UrlDecode($signatureB64);

        if ($providedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        // Standard-claim validation (SECURITY.md §4.3 required claims)
        if (!isset($payload['iss'], $payload['aud'], $payload['sub'], $payload['exp'])) {
            return null;
        }
        if ($payload['iss'] !== $this->issuer) {
            return null;
        }
        if ($payload['aud'] !== $this->audience) {
            return null;
        }
        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    private function encodeToken(array $header, array $payload): string
    {
        $headerEncoded = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $this->signature($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64UrlEncode($signature);
        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    private function signature(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
