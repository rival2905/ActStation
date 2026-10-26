<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use App\Modules\Event\Services\DraftPersonnelInvitations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckPersonnelInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        app(DraftPersonnelInvitations::class)->find((string) $this->route('token'));

        return $this->user() === null || (bool) $this->user()->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
