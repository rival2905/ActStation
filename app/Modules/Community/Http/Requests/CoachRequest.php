<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoachRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'development_level_id' => ['nullable', 'integer', 'exists:development_levels,id'],
            'gender_scope' => ['required', 'in:male,female,all'],
            'license' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,active,rejected,inactive'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
