<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class RegistrationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawPayload = $this->input('payload');
        if (is_string($rawPayload)) {
            $decoded = json_decode($rawPayload, true);
            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'payload' => 'Format payload JSON tidak valid.',
                ]);
            }

            $allowlisted = [
                'event_category_id',
                'category',
                'team_name',
                'contact_person',
                'contact_phone',
                'notes',
                'registration_source',
                'community_subtype',
                'members',
                'personnel',
            ];

            $toMerge = [];
            foreach ($allowlisted as $key) {
                if (array_key_exists($key, $decoded)) {
                    $toMerge[$key] = $decoded[$key];
                }
            }

            $this->merge($toMerge);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'payload' => ['nullable'],
            'event_category_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'registration_source' => ['nullable', 'in:club,community'],
            'community_subtype' => ['nullable', 'in:school,university,institution,club,free_association'],
            'revision' => ['nullable', 'integer'],
            'personnel' => ['nullable', 'array', 'max:200'],
            'personnel.*' => ['array'],
            'personnel.*.user_id' => ['required', 'integer', 'min:1'],
            'personnel.*.role_id' => ['required', 'integer', 'min:1'],
            'members' => ['nullable', 'array'],
            'members.*' => ['array'],
            'members.*.user_id' => ['nullable', 'integer'],
            'members.*.jersey_number' => ['nullable', 'integer', 'between:0,99'],
            'members.*.is_captain' => ['nullable', 'boolean'],
            'members.*.position' => ['nullable', 'string', 'max:50'],
            'members.*.team_role' => ['nullable', 'in:player'],
            'team_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'team_documents' => ['nullable', 'array'],
            'team_documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'athlete_documents' => ['nullable', 'array'],
            'athlete_documents.*' => ['nullable', 'array'],
            'athlete_documents.*.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'clear_files' => ['nullable', 'array'],
            'clear_files.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
