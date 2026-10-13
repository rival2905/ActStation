<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documentTypeId = $this->route('documentType')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('document_types', 'code')->ignore($documentTypeId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'in:athlete,team'],
            'applicable_entities' => ['nullable', 'array'],
            'applicable_entities.*' => ['string', 'in:club,school,university,institution,free_association'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
