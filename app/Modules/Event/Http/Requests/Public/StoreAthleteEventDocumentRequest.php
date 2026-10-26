<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreAthleteEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
    }

    public function rules(): array
    {
        $photo = $this->route('code') === 'photo';

        return ['document' => [
            'bail', 'required', 'file',
            ...($photo ? ['image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png'] : ['mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png']),
            'max:2048',
        ]];
    }

    public function messages(): array
    {
        $format = $this->route('code') === 'photo'
            ? 'Pasfoto harus berupa gambar JPG, JPEG, atau PNG. PDF tidak diperbolehkan.'
            : 'Berkas harus berupa PDF, JPG, JPEG, atau PNG.';

        return [
            'document.required' => 'Pilih berkas yang ingin diunggah.',
            'document.file' => 'Berkas tidak dapat dibaca. Pilih ulang berkas Anda.',
            'document.uploaded' => 'Unggah gagal. Pastikan ukuran berkas maksimal 2 MB, lalu pilih ulang berkas Anda.',
            'document.image' => $format,
            'document.mimes' => $format,
            'document.mimetypes' => $format,
            'document.max' => 'Ukuran berkas maksimal 2 MB. Pilih berkas yang lebih kecil.',
        ];
    }
}
