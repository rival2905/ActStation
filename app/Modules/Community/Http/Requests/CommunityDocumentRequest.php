<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use App\Modules\Community\Models\Community;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CommunityDocumentRequest extends FormRequest
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
            $community = $this->route('community');
            if ($community) {
                $communityModel = is_object($community) ? $community : Community::find($community);
                if ($communityModel && in_array($communityModel->status, ['pending', 'verified'], true)) {
                    $category = (string) $this->input('category');
                    if ($communityModel->isStatutoryCategory($category)) {
                        $v->errors()->add('category', 'Dokumen legalitas wajib tidak dapat diunggah atau diganti saat komunitas dalam peninjauan atau telah terverifikasi.');
                    }
                }
            }
        });
    }
}
