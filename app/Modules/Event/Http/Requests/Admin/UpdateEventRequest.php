<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use App\Modules\Event\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
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
        $eventId = $this->route('event')?->id ?? $this->route('event');

        return [
            'sport_category_id' => ['required', 'integer'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'venue_selection_present' => ['nullable', 'boolean'],
            'venue_ids' => ['required_if:venue_selection_present,1', 'array', 'min:1', 'max:100'],
            'venue_ids.*' => ['required', 'integer', 'distinct'],
            'venue_court_ids' => ['nullable', 'array', 'max:500'],
            'venue_court_ids.*' => ['required', 'integer', 'distinct'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('events', 'slug')->ignore($eventId)],
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

    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->route('event');

            if (! $event instanceof Event || $validator->errors()->isNotEmpty()) {
                return;
            }

            $registrationDeadline = $this->date('registration_deadline');
            $startsAt = $this->date('starts_at');
            $latestRegistrationEnd = $event->categories()->whereNotNull('registration_end')->max('registration_end');
            $latestPaymentEnd = $event->categories()->whereNotNull('payment_end')->max('payment_end');
            $latestCategoryEnd = collect([$latestRegistrationEnd, $latestPaymentEnd])->filter()->max();

            if ($registrationDeadline !== null && $latestRegistrationEnd !== null && $registrationDeadline->lt($latestRegistrationEnd)) {
                $validator->errors()->add('registration_deadline', 'Batas akhir event tidak boleh lebih awal dari tutup pendaftaran kategori.');
            }

            $hasBoundedAgeCategory = $event->categories()->where(function ($query): void {
                $query->whereNotNull('min_age')->orWhereNotNull('max_age');
            })->exists();

            if ($startsAt === null && ($latestCategoryEnd !== null || $hasBoundedAgeCategory)) {
                $validator->errors()->add('starts_at', 'Waktu mulai event wajib diisi selama kategori memiliki batas usia atau jendela pendaftaran.');
            } elseif ($startsAt !== null && $latestCategoryEnd !== null && $startsAt->lt($latestCategoryEnd)) {
                $validator->errors()->add('starts_at', 'Waktu mulai event tidak boleh lebih awal dari tutup pendaftaran atau pembayaran kategori.');
            }
        }];
    }
}
