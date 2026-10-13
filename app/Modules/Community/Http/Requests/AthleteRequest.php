<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AthleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Status dipaksa pending untuk memenuhi prinsip dua sumbu (persetujuan atlet wajib).
        if ($this->has('status')) {
            $this->merge(['status' => 'pending']);
        }
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
            'initiated_by' => ['nullable', 'in:community,athlete'],
            'status' => ['nullable', 'in:pending'],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date', 'after_or_equal:joined_at'],
        ];
    }
}
