<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
            'sport_category_id' => ['required', 'integer'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'venue_selection_present' => ['nullable', 'boolean'],
            'venue_ids' => ['required_if:venue_selection_present,1', 'array', 'min:1', 'max:100'],
            'venue_ids.*' => ['required', 'integer', 'distinct'],
            'venue_court_ids' => ['nullable', 'array', 'max:500'],
            'venue_court_ids.*' => ['required', 'integer', 'distinct'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:events,slug'],
            'description' => ['nullable', 'string'],
            'registration_open' => ['nullable', 'boolean'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'allow_manual_payment' => ['nullable', 'boolean'],
            'manual_bank_name' => ['nullable', 'string', 'max:50'],
            'manual_bank_account_number' => ['nullable', 'string', 'max:50'],
            'manual_bank_account_holder' => ['nullable', 'string', 'max:255'],
            'manual_payment_instructions' => ['nullable', 'string'],
        ];
    }
}
