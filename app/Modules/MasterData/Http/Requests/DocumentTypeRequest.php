<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $documentTypeId = $this->getDocumentTypeId();

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $documentType = $this->getDocumentTypeModel();
            if (! $documentType || ! $documentType->isInUse()) {
                return;
            }

            if ($this->exists('code') && $this->input('code') !== $documentType->code) {
                $validator->errors()->add('code', 'Kode jenis dokumen tidak dapat diubah karena sedang digunakan.');
            }

            if ($this->exists('scope') && $this->input('scope') !== $documentType->scope) {
                $validator->errors()->add('scope', 'Scope jenis dokumen tidak dapat diubah karena sedang digunakan.');
            }

            if ($this->exists('applicable_entities')) {
                $inputEntities = $this->input('applicable_entities');
                if (DocumentType::normalizeEntities($inputEntities) !== DocumentType::normalizeEntities($documentType->applicable_entities)) {
                    $validator->errors()->add('applicable_entities', 'Entitas yang berlaku tidak dapat diubah karena sedang digunakan.');
                }
            }
        });
    }

    protected function getDocumentTypeId(): mixed
    {
        $docType = $this->route('documentType') ?? $this->route('document_type');

        return $docType instanceof DocumentType ? $docType->id : $docType;
    }

    protected function getDocumentTypeModel(): ?DocumentType
    {
        $docType = $this->route('documentType') ?? $this->route('document_type');

        if ($docType instanceof DocumentType) {
            return $docType;
        }

        if (is_numeric($docType) || is_string($docType)) {
            return DocumentType::query()->find($docType);
        }

        return null;
    }
}
