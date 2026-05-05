<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateProductRequest extends FormRequest
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
            'sku'         => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9\-]+$/'],
            'name'        => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price'       => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency'    => ['sometimes', 'string', Rule::in(['TRY', 'USD', 'EUR'])],
            'stock'       => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku'         => strtoupper(trim((string) $this->input('sku', ''))),
            'name'        => strip_tags(trim((string) $this->input('name', ''))),
            'description' => $this->input('description') !== null
                ? strip_tags((string) $this->input('description'))
                : null,
        ]);
    }
}
