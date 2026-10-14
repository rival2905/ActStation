<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectOrganizerKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan penolakan wajib diisi untuk memberi tahu pemohon alasan penolakan.',
            'notes.max' => 'Catatan penolakan maksimal 1000 karakter.',
        ];
    }
}
