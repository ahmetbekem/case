<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateAddressRequest',
    required: ['title', 'fullName', 'phone', 'city', 'district', 'addressLine'],
    description: 'CreateAddressRequest ile aynı alanlar; default flag\'lere bu endpoint dokunmaz '
        . '(ayrı endpoint var: PATCH /addresses/{id}/default-delivery|billing).',
    properties: [
        new OA\Property(property: 'title', type: 'string', minLength: 2, maxLength: 50),
        new OA\Property(property: 'fullName', type: 'string', minLength: 3, maxLength: 255),
        new OA\Property(property: 'phone', type: 'string'),
        new OA\Property(property: 'city', type: 'string', maxLength: 100),
        new OA\Property(property: 'district', type: 'string', maxLength: 100),
        new OA\Property(property: 'neighborhood', type: 'string', maxLength: 150, nullable: true),
        new OA\Property(property: 'postalCode', type: 'string', pattern: '^[0-9]{5}$', nullable: true),
        new OA\Property(property: 'addressLine', type: 'string', minLength: 10, maxLength: 500),
        new OA\Property(property: 'addressLine2', type: 'string', maxLength: 255, nullable: true),
    ],
    type: 'object',
)]
final class UpdateAddressRequest extends FormRequest
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
            'title'        => ['required', 'string', 'min:2', 'max:50'],
            'fullName'     => ['required', 'string', 'min:3', 'max:255'],
            'phone'        => ['required', 'string', 'regex:/^\+?[0-9 \-()]{10,20}$/'],
            'city'         => ['required', 'string', 'max:100'],
            'district'     => ['required', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'postalCode'   => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'addressLine'  => ['required', 'string', 'min:10', 'max:500'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // ORTA Fix #7 — title XSS defense-in-depth (CreateAddressRequest ile uyumlu)
            'title'        => strip_tags(trim((string) $this->input('title', ''))),
            'fullName'     => strip_tags(trim((string) $this->input('fullName', ''))),
            'addressLine'  => strip_tags(trim((string) $this->input('addressLine', ''))),
            'addressLine2' => $this->filled('addressLine2')
                ? strip_tags(trim((string) $this->input('addressLine2')))
                : null,
            'neighborhood' => $this->filled('neighborhood')
                ? trim((string) $this->input('neighborhood'))
                : null,
            'postalCode'   => $this->filled('postalCode')
                ? trim((string) $this->input('postalCode'))
                : null,
        ]);
    }
}
