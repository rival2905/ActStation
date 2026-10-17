<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;

class OrganizerEventRequest extends SaveEventVenuesRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');
        if ($event !== null) {
            return $event->user_id === $user?->id || $user?->hasRole('superadmin');
        }

        return $user?->hasRole('organizer') && $user->kyc_status === 'verified' && ! empty($user->bank_account_number);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'sport_category_id' => ['nullable', 'integer', 'exists:sport_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue_ids' => ['required_without:venue_id', 'array', 'min:1', 'max:100'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'registration_open' => ['nullable', 'boolean'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'venue_approval_doc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'banner_image' => ['nullable', 'image', 'max:4096'],
            'allow_manual_payment' => ['nullable', 'boolean'],
            'manual_bank_name' => ['nullable', 'string', 'max:50'],
            'manual_bank_account_number' => ['nullable', 'string', 'max:50'],
            'manual_bank_account_holder' => ['nullable', 'string', 'max:255'],
            'manual_payment_instructions' => ['nullable', 'string'],
        ]);
    }
}
