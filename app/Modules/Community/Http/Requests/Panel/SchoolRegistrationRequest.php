<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SchoolRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('panel.school-registrations.consume')) {
            return [
                'token' => ['required', 'string', 'size:64'],
                'consent' => ['required', 'accepted'],
            ];
        }

        if ($this->routeIs('panel.school-registrations.evidence')) {
            return [
                'evidence' => [
                    'required',
                    'file',
                    'mimes:pdf,png,jpg,jpeg',
                    'mimetypes:application/pdf,image/png,image/jpeg',
                    'max:5120',
                ],
            ];
        }

        return [
            'action' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ];
    }
}
