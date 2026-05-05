<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegisterUserRequest',
    required: ['email', 'password', 'fullName'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 320, example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, maxLength: 128, example: 'securePass123'),
        new OA\Property(property: 'fullName', type: 'string', minLength: 2, maxLength: 255, example: 'Ali Veli'),
    ],
    type: 'object',
)]
final class RegisterUserRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'fullName' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email'    => strtolower(trim((string) $this->input('email', ''))),
            'fullName' => strip_tags(trim((string) $this->input('fullName', ''))),
        ]);
    }
}
