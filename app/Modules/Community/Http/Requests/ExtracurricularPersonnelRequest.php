<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtracurricularPersonnelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    /**
     * Prepare inputs before validation to reconcile target_user_id and user_id.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('target_user_id') && $this->has('user_id')) {
            $this->merge([
                'target_user_id' => $this->input('user_id'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('panel.schools.extracurriculars.personnel.accept')) {
            return [
                'token' => ['required', 'string', 'size:64'],
                'consent' => ['required', 'accepted'],
            ];
        }

        return [
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sports_personnel_role_id' => ['required', 'integer', 'exists:sports_personnel_roles,id'],
            'can_manage_personnel' => ['nullable', 'boolean'],
            'replaces_id' => ['nullable', 'integer', 'exists:school_extracurricular_appointments,id'],
        ];
    }
}
