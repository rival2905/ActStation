<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use App\Modules\Communication\Models\EmailMessage;
use Illuminate\Foundation\Http\FormRequest;

class RetryEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']) === true
            && $this->user()->can('setting.email');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $message = EmailMessage::findOrFail($this->route('id'));

        return [
            'confirm_resend' => in_array($message->status, ['submitted', 'unknown'], true)
                ? ['required', 'accepted'] : ['sometimes', 'accepted'],
        ];
    }
}
