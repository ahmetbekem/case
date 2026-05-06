<?php

declare(strict_types=1);

namespace Presentation\System\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateSettingRequest',
    required: ['value'],
    description: 'Ayar değer güncellemesi — yalnızca value alanı; key path param.',
    properties: [
        new OA\Property(property: 'value', type: 'string', maxLength: 5000, description: 'Raw stringified değer (json type ise JSON string)'),
    ],
    type: 'object',
)]
final class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:5000'],
        ];
    }
}
