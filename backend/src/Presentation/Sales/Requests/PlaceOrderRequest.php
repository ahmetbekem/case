<?php

declare(strict_types=1);

namespace Presentation\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'displayCurrency' => ['required', 'string', Rule::in(['TRY', 'USD', 'EUR'])],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:5000'],
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
