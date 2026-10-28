<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterEventActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('members'))) {
            $this->merge(['members' => array_values(array_filter($this->input('members'), fn ($id) => $id !== null && $id !== ''))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'parent_participant_id' => ['nullable', 'integer'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'members' => ['required', 'array', 'min:1', 'max:100'],
            'members.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'consent' => ['required', 'accepted'],
            'use_extra' => ['nullable', 'boolean'],
        ];
    }
}
