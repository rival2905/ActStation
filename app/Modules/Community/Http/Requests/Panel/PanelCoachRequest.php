<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PanelCoachRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'sports_personnel_role_id' => ['nullable', 'integer', Rule::exists('sports_personnel_roles', 'id')->where('is_active', true)],
            'development_level_id' => ['nullable', 'integer', 'exists:development_levels,id'],
            'gender_scope' => ['required', 'in:male,female,all'],
            'license' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
