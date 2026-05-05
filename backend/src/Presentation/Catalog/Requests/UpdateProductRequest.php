<?php

declare(strict_types=1);

namespace Presentation\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update — every field optional. Handler treats null as
 * "leave as-is" rather than "clear to null".
 */
final class UpdateProductRequest extends FormRequest
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
            'name'        => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price'       => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'currency'    => ['sometimes', 'string', Rule::in(['TRY', 'USD', 'EUR'])],
            'isActive'    => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('name')) {
            $merge['name'] = strip_tags(trim((string) $this->input('name', '')));
        }
        if ($this->has('description')) {
            $description = $this->input('description');
            $merge['description'] = $description !== null
                ? strip_tags((string) $description)
                : null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
