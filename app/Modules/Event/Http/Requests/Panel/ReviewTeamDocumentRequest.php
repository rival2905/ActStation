<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewTeamDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('kind') === 'participant'
            && (! $this->routeIs('admin.*') || $this->user()?->can('event-participant.confirm'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:verified,rejected'],
            'notes' => ['nullable', 'required_if:status,rejected', 'string', 'max:2000'],
            'version' => ['required', 'string', 'size:64'],
        ];
    }
}
