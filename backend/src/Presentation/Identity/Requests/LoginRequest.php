<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 320, example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', maxLength: 128, example: 'securePass123'),
    ],
    type: 'object',
)]
final class LoginRequest extends FormRequest
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
            'email'    => ['required', 'string', 'email:rfc', 'max:320'],
            'password' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email', ''))),
        ]);
    }
}
