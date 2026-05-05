<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateCartItemRequest',
    required: ['quantity'],
    description: 'Sepet kaleminin yeni quantity\'si — 0 göndermek istemiyorsan DELETE kullan.',
    properties: [
        new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 1000000, example: 3),
    ],
    type: 'object',
)]
final class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
