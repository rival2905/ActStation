<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class PanelAthleteRequest extends FormRequest
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
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'position' => ['nullable', 'string', 'max:50'],
            'joined_at' => ['nullable', 'date'],
        ];
    }
}
