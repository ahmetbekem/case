<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CancelOrderRequest',
    required: ['reason'],
    description: 'Sipariş iptali — yalnızca pending statüsündeki siparişlere uygulanır.',
    properties: [
        new OA\Property(property: 'reason', type: 'string', minLength: 3, maxLength: 500, example: 'Yanlış adrese gönderim'),
    ],
    type: 'object',
)]
final class CancelOrderRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
