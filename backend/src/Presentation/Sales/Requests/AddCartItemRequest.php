<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AddCartItemRequest',
    required: ['productId', 'quantity'],
    properties: [
        new OA\Property(property: 'productId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 1000000, example: 2),
    ],
    type: 'object',
)]
final class AddCartItemRequest extends FormRequest
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
            'productId' => ['required', 'string', 'uuid'],
            'quantity'  => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
