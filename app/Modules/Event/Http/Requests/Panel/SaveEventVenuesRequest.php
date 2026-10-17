<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEventVenuesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'venue_selection_present' => ['nullable', 'boolean'],
            'venue_ids' => ['required', 'array', 'min:1', 'max:100'],
            'venue_ids.*' => ['required', 'integer', 'distinct'],
            'venue_court_ids' => ['nullable', 'array', 'max:500'],
            'venue_court_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
