<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class JoinInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consent' => ['required', 'accepted'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'education_detail' => ['nullable', 'string', 'max:150'],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'position' => ['nullable', 'string', 'max:50'],
        ];
    }
}
