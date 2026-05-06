<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

// Currency alanı KALDIRILDI (ADR-016). Tüm ürünler system base_currency
// (varsayılan TRY) ile saklanır; admin form'da seçim yapılmaz.

#[OA\Schema(
    schema: 'CreateProductRequest',
    required: ['sku', 'name', 'price', 'stock'],
    properties: [
        new OA\Property(property: 'sku', type: 'string', minLength: 3, maxLength: 64, pattern: '^[A-Z0-9\\-]+$', example: 'BABT-SYAH-42'),
        new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255, example: 'Babet Ayakkabı'),
        new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, maximum: 999999.99, example: 1000.00),
        new OA\Property(property: 'stock', type: 'integer', minimum: 0, maximum: 1000000, example: 50),
    ],
    type: 'object',
)]
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
