<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonnelRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('community-coach.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('sports_personnel_roles')->ignore($this->route('role'))],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
