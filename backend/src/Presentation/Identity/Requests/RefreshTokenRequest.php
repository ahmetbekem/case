<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
