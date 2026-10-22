<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSchoolAuthorityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_active && $this->user()->can('community.verify')
            && $this->user()->roles->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_email' => ['required', 'email', 'max:255', Rule::exists('users', 'email')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
