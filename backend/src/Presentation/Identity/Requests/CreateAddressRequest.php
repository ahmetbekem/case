<?php

declare(strict_types=1);

namespace Presentation\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateAddressRequest',
    required: ['title', 'fullName', 'phone', 'city', 'district', 'addressLine'],
    properties: [
        new OA\Property(property: 'title', type: 'string', minLength: 2, maxLength: 50, example: 'Ev'),
        new OA\Property(property: 'fullName', type: 'string', minLength: 3, maxLength: 255, example: 'Ahmet Bekem'),
        new OA\Property(property: 'phone', type: 'string', example: '+90 555 123 45 67'),
        new OA\Property(property: 'city', type: 'string', maxLength: 100, example: 'İstanbul'),
        new OA\Property(property: 'district', type: 'string', maxLength: 100, example: 'Kadıköy'),
        new OA\Property(property: 'neighborhood', type: 'string', maxLength: 150, nullable: true, example: 'Caferağa'),
        new OA\Property(property: 'postalCode', type: 'string', pattern: '^[0-9]{5}$', nullable: true, example: '34710'),
        new OA\Property(property: 'addressLine', type: 'string', minLength: 10, maxLength: 500, example: 'Moda Caddesi No: 123 D: 5'),
        new OA\Property(property: 'addressLine2', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'setAsDefaultDelivery', type: 'boolean', default: false),
        new OA\Property(property: 'setAsDefaultBilling', type: 'boolean', default: false),
    ],
    type: 'object',
)]
final class CreateAddressRequest extends FormRequest
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
            'title'                => ['required', 'string', 'min:2', 'max:50'],
            'fullName'             => ['required', 'string', 'min:3', 'max:255'],
            // Phone: regex defansif filter; PhoneNumber VO daha sıkı normalize eder.
            'phone'                => ['required', 'string', 'regex:/^\+?[0-9 \-()]{10,20}$/'],
            'city'                 => ['required', 'string', 'max:100'],
            'district'             => ['required', 'string', 'max:100'],
            'neighborhood'         => ['nullable', 'string', 'max:150'],
            'postalCode'           => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'addressLine'          => ['required', 'string', 'min:10', 'max:500'],
            'addressLine2'         => ['nullable', 'string', 'max:255'],
            'setAsDefaultDelivery' => ['nullable', 'boolean'],
            'setAsDefaultBilling'  => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title'        => trim((string) $this->input('title', '')),
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
