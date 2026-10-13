<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class PanelProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'province_id' => ['required', 'string', 'max:10'],
            'city_id' => ['required', 'string', 'max:10'],
            'subdistrict_id' => ['nullable', 'string', 'max:15'],
            'village_id' => ['nullable', 'string', 'max:15'],
            'postal_code' => ['nullable', 'string', 'max:5'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'npsn' => ['nullable', 'string', 'max:20'],
        ];
    }
}
