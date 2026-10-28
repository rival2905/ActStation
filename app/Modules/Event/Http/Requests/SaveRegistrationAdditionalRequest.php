<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveRegistrationAdditionalRequest extends FormRequest
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
            'revision' => ['required', 'integer'],
            'institution_version' => ['nullable', 'string', 'size:64'],
            'institution_answers' => ['nullable', 'array', 'max:200'],
            'team_answers' => ['nullable', 'array', 'max:200'],
            'selected_activity_ids' => ['nullable', 'array', 'max:100'],
            'selected_activity_ids.*' => ['nullable', 'integer', 'min:1'],
            'complete' => ['nullable', 'boolean'],
        ];
    }
}
