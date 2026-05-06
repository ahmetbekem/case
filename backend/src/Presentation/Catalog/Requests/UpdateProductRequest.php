<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

// Partial update — her alan opsiyonel. Currency alanı KALDIRILDI (ADR-016):
// ürün currency'si system base_currency'ye sabitlendi.

#[OA\Schema(
    schema: 'UpdateProductRequest',
    description: 'Tüm alanlar opsiyonel. Sadece gönderilenler güncellenir; '
        . 'isActive ile pasifleştirme/aktifleştirme tek endpoint üzerinden yapılır.',
    properties: [
        new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255),
        new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, maximum: 999999.99),
        new OA\Property(property: 'isActive', type: 'boolean'),
    ],
    type: 'object',
)]
final class UpdateProductRequest extends FormRequest
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
            'name'        => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price'       => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'isActive'    => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('name')) {
            $merge['name'] = strip_tags(trim((string) $this->input('name', '')));
        }
        if ($this->has('description')) {
            $description = $this->input('description');
            $merge['description'] = $description !== null
                ? strip_tags((string) $description)
                : null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
