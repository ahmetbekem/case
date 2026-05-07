<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PlaceOrderRequest',
    required: ['displayCurrency', 'deliveryAddressId'],
    properties: [
        new OA\Property(
            property: 'displayCurrency',
            type: 'string',
            enum: ['TRY', 'USD', 'EUR'],
            description: 'Siparişin görüntü para birimi — kur snapshot\'ı sipariş anında dondurulur',
            example: 'TRY',
        ),
        new OA\Property(
            property: 'deliveryAddressId',
            type: 'string',
            format: 'uuid',
            description: 'ADR-018 — Teslimat adresi ID. IDOR-safe (başka kullanıcının ID\'si 404).',
        ),
        new OA\Property(
            property: 'billingAddressId',
            type: 'string',
            format: 'uuid',
            nullable: true,
            description: 'Fatura adresi ID. null verilirse delivery ile aynı kabul edilir.',
        ),
        new OA\Property(property: 'notes', type: 'string', maxLength: 5000, nullable: true),
    ],
    type: 'object',
)]
final class PlaceOrderRequest extends FormRequest
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
            'displayCurrency'   => ['required', 'string', Rule::in(['TRY', 'USD', 'EUR'])],
            'deliveryAddressId' => ['required', 'string', 'uuid'],
            'billingAddressId'  => ['sometimes', 'nullable', 'string', 'uuid'],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = ['displayCurrency' => strtoupper(trim((string) $this->input('displayCurrency', '')))];
        if ($this->has('notes')) {
            $notes = $this->input('notes');
            $merge['notes'] = $notes !== null ? strip_tags((string) $notes) : null;
        }
        $this->merge($merge);
    }
}
