<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
