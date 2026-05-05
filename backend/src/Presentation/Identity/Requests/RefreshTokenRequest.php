<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RefreshTokenRequest',
    required: ['refresh_token'],
    properties: [
        new OA\Property(
            property: 'refresh_token',
            type: 'string',
            description: '64 karakter hex (32 random byte) — RefreshToken::generate() ile uyumlu',
            pattern: '^[a-f0-9]{64}$',
            example: 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234abcd',
        ),
    ],
    type: 'object',
)]
final class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Hex 64 chars = 32 random bytes — matches RefreshToken::generate()
            'refresh_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/i'],
        ];
    }
}
