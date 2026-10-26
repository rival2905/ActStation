<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class RegisterPersonnelInvitationRequest extends CheckPersonnelInvitationRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return parent::authorize() && $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone_number');
        $digits = is_string($phone) ? preg_replace('/[^0-9]/', '', $phone) : '';
        $this->merge(['phone_normalized' => $digits === '' ? null : (str_starts_with($digits, '0') ? '+62'.substr($digits, 1) : '+'.$digits)]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'min:9', 'max:20', 'regex:/^\+?[0-9 ()-]+$/'],
            'phone_normalized' => ['required', 'string', 'regex:/^\+[1-9][0-9]{8,14}$/', 'unique:users,phone_normalized'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role_id' => ['required', 'integer', 'min:1'],
            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar. Masuk dengan akun Anda melalui link undangan ini.',
            'phone_normalized.unique' => 'Nomor WhatsApp sudah terdaftar. Gunakan akun yang sudah ada.',
            'phone_normalized.regex' => 'Nomor WhatsApp tidak valid. Gunakan format 08 atau kode negara.',
            'consent.accepted' => 'Persetujuan menjadi tenaga pendukung wajib dicentang.',
            'consent.required' => 'Persetujuan menjadi tenaga pendukung wajib dicentang.',
        ];
    }
}
