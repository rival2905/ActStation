<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SchoolAuthorityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('panel.school-registrations.official-email')) {
            return [
                'new_email' => ['required', 'email', 'max:255'],
                'current_password' => ['required', 'string', 'current_password:web'],
                'target_email' => ['prohibited'],
                'target_user_id' => ['prohibited'],
            ];
        }

        return $this->routeIs('panel.school-authority.accept')
            ? ['token' => ['required', 'string', 'size:64'], 'consent' => ['required', 'accepted']]
            : [
                'target_email' => ['required_without:target_user_id', 'nullable', 'email', 'max:255'],
                'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
                'new_email' => ['nullable', 'email', 'max:255'],
                'current_password' => ['required', 'string', 'current_password:web'],
            ];
    }
}
