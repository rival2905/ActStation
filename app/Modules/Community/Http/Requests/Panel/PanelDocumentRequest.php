<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\CommunityLookupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PanelDocumentRequest extends FormRequest
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
        return [
            'category' => ['required', 'string', 'in:deed,decree,permit,id_card,birth_certificate,other,club_registration,legal_entity_proof,institution_decree,founding_statement,appointment_decree,business_registration'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $communityId = CommunityLookupService::resolveActiveCommunityId($this->user()?->id);

            if ($communityId) {
                $community = Community::find($communityId);
                if ($community && in_array($community->status, ['pending', 'verified'], true)) {
                    $category = (string) $this->input('category');
                    if ($community->isStatutoryCategory($category)) {
                        $v->errors()->add('category', 'Dokumen legalitas wajib tidak dapat diunggah atau diganti saat komunitas dalam peninjauan atau telah terverifikasi.');
                    }
                }
            }
        });
    }
}
