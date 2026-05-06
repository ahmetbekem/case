<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use OpenApi\Attributes as OA;

// Login + Register cevabı — token çifti + user kaydı tek round-trip'te.

#[OA\Schema(
    schema: 'TokenIssueResultDTO',
    title: 'Token + User',
    description: 'Login + Register cevabı — token çifti + user kaydı tek round-trip\'te.',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/UserDTO'),
        new OA\Property(property: 'accessToken', type: 'string', description: 'JWT access token (HS256)'),
        new OA\Property(property: 'accessTokenExpiresIn', type: 'integer', description: 'TTL (saniye)', example: 900),
        new OA\Property(property: 'refreshToken', type: 'string', description: '64 karakter hex (rotation)'),
        new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'cartMerged', type: 'boolean', description: 'true ise login/register sırasında guest cart user cart ile birleştirildi (ADR-017)'),
    ],
    type: 'object',
)]
final readonly class TokenIssueResultDTO extends AbstractOutputDTO
{
    public function __construct(
        public UserDTO $user,
        public string $accessToken,
        public int $accessTokenExpiresIn,
        public string $refreshToken,
        public string $tokenType = 'Bearer',
        public bool $cartMerged = false,
    ) {
    }
}
