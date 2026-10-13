<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public const ALLOWED_COMMUNITY_SUBTYPES = [
        'school',
        'university',
        'institution',
        'club',
        'free_association',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('allow_club') && ! $this->has('allow_community')) {
            $this->merge([
                'allow_club' => true,
                'allow_community' => false,
            ]);
        }

        if ($this->input('registration_fee') === null || ! $this->has('registration_fee')) {
            $this->merge([
                'registration_fee' => 0,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxRosterRules = ['nullable', 'integer', 'min:1'];
        if ($this->filled('min_roster')) {
            $maxRosterRules[] = 'gte:min_roster';
        }

        $maxAgeRules = ['nullable', 'integer', 'min:0'];
        if ($this->filled('min_age')) {
            $maxAgeRules[] = 'gte:min_age';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'match_type' => ['required', 'in:5x5,3x3'],
            'gender' => ['required', 'in:male,female,mixed'],
            'development_level_id' => ['nullable', 'integer', 'exists:development_levels,id'],
            'min_age' => ['nullable', 'integer', 'min:0'],
            'max_age' => $maxAgeRules,
            'registration_fee' => ['required', 'numeric', 'min:0'],
            'registration_start' => ['nullable', 'date'],
            'registration_end' => ['nullable', 'date'],
            'payment_start' => ['nullable', 'date'],
            'payment_end' => ['nullable', 'date'],
            'min_roster' => ['nullable', 'integer', 'min:1'],
            'max_roster' => $maxRosterRules,
            'max_teams' => ['nullable', 'integer', 'min:2'],
            'is_roster_public' => ['nullable', 'boolean'],
            'registration_flow' => ['required', 'in:screening_first,payment_first,instant_free,by_invitation'],
            'enable_waiting_list' => ['nullable', 'boolean'],
            'payment_time_limit_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string', 'max:50', 'distinct', 'exists:document_types,code'],
            'allow_club' => ['nullable', 'boolean'],
            'allow_community' => ['nullable', 'boolean'],
            'community_subtype' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSourceBooleans($validator);
            $this->validateCommunitySubtype($validator);
            $this->validateFlowAndPricing($validator);
            $this->validateAgeRestrictions($validator);
            $this->validateScheduleAndDeadlines($validator);
            $this->validateRiskyEdits($validator);
        });
    }

    protected function validateSourceBooleans(Validator $validator): void
    {
        $allowClub = $this->boolean('allow_club');
        $allowCommunity = $this->boolean('allow_community');

        if (! $allowClub && ! $allowCommunity) {
            $validator->errors()->add('allow_club', 'Setidaknya salah satu sumber tim (Klub atau Komunitas) harus diizinkan.');
        }
    }

    protected function validateCommunitySubtype(Validator $validator): void
    {
        if ($this->boolean('allow_community')) {
            $subtype = (string) $this->input('community_subtype', '');
            if ($subtype === '') {
                $validator->errors()->add('community_subtype', 'Subtipe komunitas wajib dipilih jika komunitas diizinkan.');
            } elseif (! in_array($subtype, self::ALLOWED_COMMUNITY_SUBTYPES, true)) {
                $validator->errors()->add('community_subtype', 'Subtipe komunitas tidak valid.');
            }
        }
    }

    protected function validateFlowAndPricing(Validator $validator): void
    {
        $flow = $this->input('registration_flow');
        $fee = (float) $this->input('registration_fee', 0);

        if ($flow === 'instant_free' && $fee > 0) {
            $validator->errors()->add('registration_fee', 'Kategori instant_free wajib gratis (biaya Rp 0).');
        }

        if ($flow === 'payment_first' && $fee <= 0) {
            $validator->errors()->add('registration_fee', 'Kategori payment_first mensyaratkan biaya lebih dari Rp 0.');
        }
    }

    protected function validateAgeRestrictions(Validator $validator): void
    {
        if ($this->filled('min_age') || $this->filled('max_age')) {
            $event = $this->getEvent();
            if (empty($event?->starts_at)) {
                $validator->errors()->add('min_age', 'Waktu mulai event wajib ditentukan sebelum mengatur batasan usia kategori.');
            }
        }
    }

    protected function validateScheduleAndDeadlines(Validator $validator): void
    {
        $flow = $this->input('registration_flow');
        $fee = (float) $this->input('registration_fee', 0);
        $isPaid = $fee > 0;

        $requiresFourDates = ($flow === 'payment_first') || ($flow === 'by_invitation' && $isPaid);
        $isScreeningPaid = ($flow === 'screening_first' && $isPaid);

        if ($isScreeningPaid && ! $this->filled('payment_time_limit_hours')) {
            $validator->errors()->add('payment_time_limit_hours', 'Tenggat pembayaran (jam) wajib diisi untuk kurasi berkas berbayar.');
        }

        if (! $requiresFourDates) {
            return;
        }

        $event = $this->getEvent();
        if (empty($event?->starts_at)) {
            $validator->errors()->add('registration_start', 'Waktu mulai event wajib ditentukan sebelum mengatur jadwal pendaftaran berbayar.');

            return;
        }

        $regStartInput = $this->input('registration_start');
        $regEndInput = $this->input('registration_end');
        $payStartInput = $this->input('payment_start');
        $payEndInput = $this->input('payment_end');

        if (empty($regStartInput)) {
            $validator->errors()->add('registration_start', 'Waktu mulai pendaftaran wajib diisi untuk mode ini.');
        }
        if (empty($regEndInput)) {
            $validator->errors()->add('registration_end', 'Waktu tutup pendaftaran wajib diisi untuk mode ini.');
        }
        if (empty($payStartInput)) {
            $validator->errors()->add('payment_start', 'Waktu mulai pembayaran wajib diisi untuk mode ini.');
        }
        if (empty($payEndInput)) {
            $validator->errors()->add('payment_end', 'Waktu tutup pembayaran wajib diisi untuk mode ini.');
        }

        if (empty($regStartInput) || empty($regEndInput) || empty($payStartInput) || empty($payEndInput)) {
            return;
        }

        try {
            $regStart = Carbon::parse($regStartInput);
            $regEnd = Carbon::parse($regEndInput);
            $payStart = Carbon::parse($payStartInput);
            $payEnd = Carbon::parse($payEndInput);
        } catch (\Throwable) {
            return;
        }

        if ($regStart->gte($regEnd)) {
            $validator->errors()->add('registration_end', 'Waktu tutup pendaftaran harus setelah waktu mulai pendaftaran.');
        }

        if ($regStart->gt($payStart)) {
            $validator->errors()->add('payment_start', 'Waktu mulai pembayaran tidak boleh lebih awal dari waktu mulai pendaftaran.');
        }

        if ($payStart->gte($regEnd)) {
            $validator->errors()->add('payment_start', 'Waktu mulai pembayaran harus sebelum waktu tutup pendaftaran.');
        }

        if (! $regEnd->equalTo($payEnd)) {
            $validator->errors()->add('payment_end', 'Waktu tutup pembayaran harus sama dengan waktu tutup pendaftaran.');
        }

        $eventStartsAt = Carbon::parse($event->starts_at);
        if ($regEnd->gt($eventStartsAt)) {
            $validator->errors()->add('registration_end', 'Waktu tutup pendaftaran tidak boleh melebihi tanggal mulai event.');
        }

        if ($event->registration_deadline) {
            $eventDeadline = Carbon::parse($event->registration_deadline);
            if ($regEnd->gt($eventDeadline)) {
                $validator->errors()->add('registration_end', 'Waktu tutup pendaftaran tidak boleh melebihi batas pendaftaran event.');
            }
        }
    }

    protected function validateRiskyEdits(Validator $validator): void
    {
        $category = $this->getCategory();
        if (! $category) {
            return;
        }

        $hasParticipants = $category->participants()->exists();
        $hasInvoices = false;
        if ($hasParticipants) {
            $hasInvoices = Transaction::query()
                ->where('payable_type', 'event_participant')
                ->whereIn('payable_id', $category->participants()->select('id'))
                ->exists();
        }

        if (! $hasParticipants && ! $hasInvoices) {
            return;
        }

        if ($this->has('registration_flow') && $this->input('registration_flow') !== $category->registration_flow) {
            $validator->errors()->add('registration_flow', 'Alur registrasi tidak dapat diubah karena kategori sudah memiliki peserta terdaftar atau tagihan.');
        }

        if (
            $this->hasDateChanged($this->input('registration_start'), $category->registration_start)
            || $this->hasDateChanged($this->input('registration_end'), $category->registration_end)
            || $this->hasDateChanged($this->input('payment_start'), $category->payment_start)
            || $this->hasDateChanged($this->input('payment_end'), $category->payment_end)
        ) {
            $validator->errors()->add('registration_end', 'Jadwal pendaftaran tidak dapat diubah karena kategori sudah memiliki peserta terdaftar atau tagihan.');
        }

        if (
            $category->payment_time_limit_hours !== null
            && $this->filled('payment_time_limit_hours')
            && (int) $this->input('payment_time_limit_hours') !== (int) $category->payment_time_limit_hours
        ) {
            $validator->errors()->add('payment_time_limit_hours', 'Tenggat pembayaran tidak dapat diubah karena kategori sudah memiliki peserta terdaftar atau tagihan.');
        }

        if ($this->has('match_type') && $this->input('match_type') !== $category->match_type) {
            $validator->errors()->add('match_type', 'Format tanding tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        }

        if ($this->has('gender') && $this->input('gender') !== $category->gender) {
            $validator->errors()->add('gender', 'Gender tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        }

        $allowClub = $this->boolean('allow_club');
        $allowComm = $this->boolean('allow_community');
        if ($allowClub !== (bool) $category->allow_club || $allowComm !== (bool) $category->allow_community) {
            $validator->errors()->add('allow_club', 'Sumber tim tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        } elseif ($allowComm && $this->input('community_subtype') !== $category->community_subtype) {
            $validator->errors()->add('community_subtype', 'Subtipe komunitas tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        }

        if ($this->has('min_age') || $this->has('max_age')) {
            $inputMinAge = $this->filled('min_age') ? (int) $this->input('min_age') : null;
            $modelMinAge = $category->min_age !== null ? (int) $category->min_age : null;
            $inputMaxAge = $this->filled('max_age') ? (int) $this->input('max_age') : null;
            $modelMaxAge = $category->max_age !== null ? (int) $category->max_age : null;
            if ($inputMinAge !== $modelMinAge || $inputMaxAge !== $modelMaxAge) {
                $validator->errors()->add('min_age', 'Batasan usia tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
        }

        if ($this->has('min_roster') || $this->has('max_roster')) {
            $inputMinRoster = $this->filled('min_roster') ? (int) $this->input('min_roster') : null;
            $modelMinRoster = $category->min_roster !== null ? (int) $category->min_roster : null;
            $inputMaxRoster = $this->filled('max_roster') ? (int) $this->input('max_roster') : null;
            $modelMaxRoster = $category->max_roster !== null ? (int) $category->max_roster : null;
            if ($inputMinRoster !== $modelMinRoster || $inputMaxRoster !== $modelMaxRoster) {
                $validator->errors()->add('min_roster', 'Batasan roster tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
        }
        if ($this->has('required_documents')) {
            $inputDocs = array_values(array_filter((array) $this->input('required_documents', [])));
            sort($inputDocs);
            $modelDocs = array_values(array_filter((array) ($category->required_documents ?? [])));
            sort($modelDocs);
            if ($inputDocs !== $modelDocs) {
                $validator->errors()->add('required_documents', 'Dokumen wajib tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
        }
    }

    protected function hasDateChanged(mixed $inputDate, mixed $modelDate): bool
    {
        if (empty($inputDate) && empty($modelDate)) {
            return false;
        }
        if (empty($inputDate) || empty($modelDate)) {
            return true;
        }
        try {
            return ! Carbon::parse($inputDate)->equalTo(Carbon::parse($modelDate));
        } catch (\Throwable) {
            return true;
        }
    }

    public function getCategory(): ?EventCategory
    {
        $category = $this->route('category');
        if ($category instanceof EventCategory) {
            return $category;
        }
        if (is_numeric($category) || is_string($category)) {
            return EventCategory::query()->find($category);
        }

        return null;
    }

    public function getEvent(): ?Event
    {
        $event = $this->route('event');
        if ($event instanceof Event) {
            return $event;
        }
        if (is_numeric($event) || is_string($event)) {
            return Event::query()->find($event);
        }

        return $this->getCategory()?->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryData(bool $storeDefaults = false): array
    {
        $data = $this->validated();
        $data['allow_club'] = $this->boolean('allow_club', $storeDefaults);
        $data['allow_community'] = $this->boolean('allow_community', $storeDefaults);
        $data['community_subtype'] = $data['allow_community'] ? ($data['community_subtype'] ?? null) : null;
        $data['enable_waiting_list'] = $this->boolean('enable_waiting_list', $storeDefaults);
        $data['is_roster_public'] = $this->boolean('is_roster_public', false);
        $data['required_documents'] = $data['required_documents'] ?? [];
        $data['registration_fee'] = (float) ($data['registration_fee'] ?? 0);

        $flow = $data['registration_flow'];
        $fee = (float) $data['registration_fee'];
        $isPaid = $fee > 0;

        if ($flow === 'payment_first' || ($flow === 'by_invitation' && $isPaid)) {
            $data['payment_time_limit_hours'] = null;
        } elseif ($flow === 'screening_first' && $isPaid) {
            $data['registration_start'] = null;
            $data['registration_end'] = null;
            $data['payment_start'] = null;
            $data['payment_end'] = null;
            if ($storeDefaults) {
                $data['payment_time_limit_hours'] ??= 24;
            }
        } else {
            $data['registration_start'] = null;
            $data['registration_end'] = null;
            $data['payment_start'] = null;
            $data['payment_end'] = null;
            $data['payment_time_limit_hours'] = null;
        }

        if ($storeDefaults) {
            $data['min_roster'] ??= $data['match_type'] === '3x3' ? 3 : 5;
            $data['max_roster'] ??= $data['match_type'] === '3x3' ? 4 : 12;
        }

        return $data;
    }
}
