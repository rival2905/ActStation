<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class DraftAthleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'revision' => ['required_without:If-Match', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('revision') && $this->header('If-Match') !== null) {
            $this->merge(['revision' => (int) trim((string) $this->header('If-Match'), '"')]);
        }
    }
}
