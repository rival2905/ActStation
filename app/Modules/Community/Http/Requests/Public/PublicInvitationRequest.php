<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class PublicInvitationRequest extends FormRequest
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
            'consent' => ['required', 'accepted'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'education_detail' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'position' => ['nullable', 'string', 'max:50'],
            'website_address' => ['nullable', 'string'],
        ];
    }
}
