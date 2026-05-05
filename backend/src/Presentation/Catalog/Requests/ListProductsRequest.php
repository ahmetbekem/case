<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListProductsRequest',
    description: 'Query string olarak gönderilen filtre+sayfalama parametreleri.',
    properties: [
        new OA\Property(property: 'page', type: 'integer', minimum: 1, default: 1),
        new OA\Property(property: 'pageSize', type: 'integer', minimum: 1, default: 20, description: 'Server-side cap: 100'),
        new OA\Property(property: 'search', type: 'string', maxLength: 255, nullable: true, description: 'Ürün adı içinde arama'),
        new OA\Property(property: 'activeOnly', type: 'boolean', default: true, description: 'Public okuma true; admin false geçirip pasifleri de görebilir'),
    ],
    type: 'object',
)]
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
