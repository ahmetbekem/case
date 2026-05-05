<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListProductsRequest extends FormRequest
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
            'page'       => ['sometimes', 'integer', 'min:1'],
            // No max here — ListProductsHandler caps at 100 server-side, so
            // a client passing pageSize=500 still gets a successful response
            // with pageSize=100 instead of a brittle 422.
            'pageSize'   => ['sometimes', 'integer', 'min:1'],
            'search'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'activeOnly' => ['sometimes', 'boolean'],
        ];
    }
}
