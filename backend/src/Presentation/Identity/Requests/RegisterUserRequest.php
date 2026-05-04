<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
