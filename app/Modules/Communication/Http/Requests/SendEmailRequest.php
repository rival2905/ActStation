<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use App\Modules\Communication\Models\EmailMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setting.email') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'string', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', 'string', Rule::in(EmailMessage::VALID_TYPES)],
            'action_url' => [
                'nullable',
                'string',
                'max:2048',
                'regex:/^https?:\/\//i',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value)) {
                        $lowered = strtolower(trim($value));
                        if (
                            str_starts_with($lowered, 'javascript:')
                            || str_starts_with($lowered, 'data:')
                            || str_starts_with($lowered, 'vbscript:')
                            || ! filter_var($value, FILTER_VALIDATE_URL)
                        ) {
                            $fail('Action URL contains unsafe protocol or invalid URL.');
                        }
                    }
                },
            ],
            'action_label' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_email.required' => 'Email penerima wajib diisi.',
            'recipient_email.email' => 'Format email penerima tidak valid.',
            'subject.required' => 'Subjek email wajib diisi.',
            'body.required' => 'Isi email wajib diisi.',
            'type.in' => 'Tipe email tidak valid.',
            'action_url.regex' => 'Action URL harus diawali dengan http:// atau https://.',
            'attachments.max' => 'Maksimal 3 lampiran berkas.',
            'attachments.*.mimes' => 'Format lampiran hanya diperbolehkan PDF, PNG, atau JPEG.',
            'attachments.*.max' => 'Ukuran setiap berkas lampiran maksimal 5MB.',
        ];
    }
}
