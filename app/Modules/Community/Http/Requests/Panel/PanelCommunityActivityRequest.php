<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PanelCommunityActivityRequest extends FormRequest
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
            'sport_category_id' => [
                'required',
                'integer',
                Rule::exists('sport_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:scheduled,ongoing,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sport_category_id.required' => 'Cabang olahraga wajib dipilih.',
            'sport_category_id.integer' => 'Cabang olahraga tidak valid.',
            'sport_category_id.exists' => 'Cabang olahraga yang dipilih tidak aktif atau tidak ditemukan.',
            'title.required' => 'Nama program aktivitas wajib diisi.',
            'title.max' => 'Nama program aktivitas maksimal 255 karakter.',
            'description.max' => 'Deskripsi program aktivitas maksimal 1000 karakter.',
            'schedule.max' => 'Jadwal latihan maksimal 255 karakter.',
            'location.max' => 'Lokasi latihan maksimal 255 karakter.',
        ];
    }
}
