<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use OpenApi\Attributes as OA;

// Refresh response — yeni access+refresh çifti, user payload yok
// (caller zaten kim olduğunu biliyor; gerekirse /auth/me çağırır).

#[OA\Schema(
    schema: 'AuthTokenPairDTO',
    title: 'Token Pair',
    description: 'Refresh cevabı — yeni access+refresh çifti, user payload yok.',
    properties: [
        new OA\Property(property: 'accessToken', type: 'string', description: 'JWT access token (HS256)'),
        new OA\Property(property: 'accessTokenExpiresIn', type: 'integer', description: 'TTL (saniye)', example: 900),
        new OA\Property(property: 'refreshToken', type: 'string', description: '64 karakter hex (rotation)'),
        new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
    ],
    type: 'object',
)]
final readonly class AuthTokenPairDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $accessToken,
        public int $accessTokenExpiresIn,
        public string $refreshToken,
        public string $tokenType = 'Bearer',
    ) {
    }
}
