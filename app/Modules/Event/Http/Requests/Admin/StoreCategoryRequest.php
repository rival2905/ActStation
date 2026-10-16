<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use App\Contracts\EntityRequirements;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public const ALLOWED_COMMUNITY_SUBTYPES = [
        'school',
        'school_sd',
        'school_smp',
        'school_sma',
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
        if (! $this->has('allow_club') && ! $this->has('allow_community') && ! $this->has('allow_individual')) {
            $this->merge([
                'allow_club' => true,
                'allow_community' => false,
                'allow_individual' => false,
            ]);
        }

        if ($this->input('registration_fee') === null || ! $this->has('registration_fee')) {
            $this->merge([
                'registration_fee' => 0,
            ]);
        }

        if (($this->has('has_allowed_institution_types') || $this->has('clear_allowed_institution_types')) && ! $this->has('allowed_institution_types')) {
            $this->merge([
                'allowed_institution_types' => [],
            ]);
        }

        if (($this->has('has_required_entity_documents') || $this->has('clear_required_entity_documents')) && ! $this->has('required_entity_documents')) {
            $this->merge([
                'required_entity_documents' => [],
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
            'max_teams_per_entity' => ['nullable', 'integer', 'min:1'],
            'is_roster_public' => ['nullable', 'boolean'],
            'registration_flow' => ['required', 'in:screening_first,payment_first,instant_free,by_invitation'],
            'enable_waiting_list' => ['nullable', 'boolean'],
            'payment_time_limit_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string', 'max:50', 'distinct', 'exists:document_types,code'],
            'document_instructions' => ['nullable', 'string', 'max:500'],
            'allow_club' => ['nullable', 'boolean'],
            'allow_community' => ['nullable', 'boolean'],
            'allow_individual' => ['nullable', 'boolean'],
            'community_subtype' => ['nullable', 'string', 'max:50'],
            'allowed_community_subtypes' => ['nullable', 'array'],
            'allowed_community_subtypes.*' => ['string', 'distinct', Rule::in(array_values(array_unique(array_merge(array_keys(EntityRequirements::CATEGORY_SUBTYPES), ['school']))))],
            'allowed_institution_types' => ['nullable', 'array'],
            'allowed_institution_types.*' => ['string', 'distinct', Rule::in(array_keys(EntityRequirements::INSTITUTION_TYPES))],
            'required_entity_documents' => ['nullable', 'array'],
            'required_entity_documents.*' => ['string', 'max:50', 'distinct', Rule::in(array_keys(EntityRequirements::DOCUMENT_LABELS))],
            'has_allowed_institution_types' => ['nullable', 'boolean'],
            'has_required_entity_documents' => ['nullable', 'boolean'],
            'clear_allowed_institution_types' => ['nullable', 'boolean'],
            'clear_required_entity_documents' => ['nullable', 'boolean'],
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
            $this->validateActiveRequiredDocuments($validator);
            $this->validateMaxTeamsPerEntity($validator);
        });
    }

    protected function validateActiveRequiredDocuments(Validator $validator): void
    {
        $category = $this->getCategory();
        $existingDocs = (array) ($category?->required_documents ?? []);
        $inputDocs = (array) $this->input('required_documents', []);
        $newDocs = array_diff($inputDocs, $existingDocs);
        if (! empty($newDocs)) {
            $inactiveCount = DocumentType::query()
                ->whereIn('code', $newDocs)
                ->where('is_active', false)
                ->count();
            if ($inactiveCount > 0) {
                $validator->errors()->add('required_documents', 'Dokumen wajib baru yang dipilih harus berstatus aktif.');
            }
        }
    }

    protected function validateMaxTeamsPerEntity(Validator $validator): void
    {
        if ($this->filled('max_teams_per_entity') && $this->filled('max_teams')) {
            if ((int) $this->input('max_teams_per_entity') > (int) $this->input('max_teams')) {
                $validator->errors()->add('max_teams_per_entity', 'Batas maksimal tim per komunitas tidak boleh melebihi total kuota tim kategori.');
            }
        }

        $category = $this->getCategory();
        if (! $category) {
            return;
        }

        if ($this->filled('max_teams_per_entity')) {
            $newMax = (int) $this->input('max_teams_per_entity');
            $maxActive = $category->maxActiveTeamsPerCommunity();
            if ($newMax < $maxActive) {
                $validator->errors()->add(
                    'max_teams_per_entity',
                    "Batas maksimal tim per komunitas ({$newMax}) tidak boleh lebih kecil dari jumlah tim aktif yang sudah terdaftar ({$maxActive} tim)."
                );
            }
        }
    }

    protected function validateSourceBooleans(Validator $validator): void
    {
        $allowClub = $this->boolean('allow_club');
        $allowCommunity = $this->boolean('allow_community');
        $allowIndividual = $this->boolean('allow_individual');

        if (! $allowClub && ! $allowCommunity && ! $allowIndividual) {
            $validator->errors()->add('allow_club', 'Setidaknya salah satu sumber tim/peserta (Klub, Komunitas, atau Atlet Mandiri) harus diizinkan.');
        }

        if ($allowIndividual && $this->input('registration_flow') === 'by_invitation') {
            $validator->errors()->add('allow_individual', 'Pendaftaran atlet mandiri saat ini tidak didukung untuk alur undangan (by invitation).');
        }

        if ($allowIndividual) {
            $category = $this->getCategory();
            $effectiveMinRoster = $this->filled('min_roster')
                ? (int) $this->input('min_roster')
                : (int) ($category?->min_roster ?? 1);

            if ($effectiveMinRoster > 1) {
                $validator->errors()->add('min_roster', 'Kategori yang mengizinkan atlet mandiri tidak boleh memiliki batas minimal roster lebih dari 1.');
            }
        }
    }

    protected function validateCommunitySubtype(Validator $validator): void
    {
        if ($this->boolean('allow_community')) {
            if ($this->has('allowed_community_subtypes')) {
                if (! is_array($this->input('allowed_community_subtypes'))) {
                    return;
                }
                $subtypes = array_filter($this->input('allowed_community_subtypes'));
                if (empty($subtypes)) {
                    $validator->errors()->add('allowed_community_subtypes', 'Setidaknya satu subtipe komunitas harus dipilih jika komunitas diizinkan.');
                }
            } else {
                $subtype = (string) $this->input('community_subtype', '');
                if ($subtype === '') {
                    $validator->errors()->add('community_subtype', 'Subtipe komunitas wajib dipilih jika komunitas diizinkan.');
                } elseif (! in_array($subtype, self::ALLOWED_COMMUNITY_SUBTYPES, true)) {
                    $validator->errors()->add('community_subtype', 'Subtipe komunitas tidak valid.');
                }
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
        $allowIndiv = $this->boolean('allow_individual');

        if ($this->has('allow_individual') && $allowIndiv !== (bool) $category->allow_individual) {
            $validator->errors()->add('allow_individual', 'Opsi pendaftaran atlet mandiri tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        }

        if ($allowClub !== (bool) $category->allow_club || $allowComm !== (bool) $category->allow_community) {
            $validator->errors()->add('allow_club', 'Sumber tim tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        } elseif ($allowComm && $this->input('community_subtype') !== $category->community_subtype && ! $this->has('allowed_community_subtypes')) {
            $validator->errors()->add('community_subtype', 'Subtipe komunitas tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
        }

        if ($this->has('allowed_community_subtypes') && is_array($this->input('allowed_community_subtypes'))) {
            $inputSubtypes = array_values(array_filter($this->input('allowed_community_subtypes', [])));
            sort($inputSubtypes);
            $modelSubtypes = array_values(array_filter((array) ($category->allowed_community_subtypes ?? [])));
            sort($modelSubtypes);
            if ($inputSubtypes !== $modelSubtypes) {
                $validator->errors()->add('allowed_community_subtypes', 'Pilihan subtipe komunitas tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
        }

        if ($this->has('allowed_institution_types') && is_array($this->input('allowed_institution_types'))) {
            $inputInst = array_values(array_filter($this->input('allowed_institution_types', [])));
            sort($inputInst);
            $modelInst = array_values(array_filter((array) ($category->allowed_institution_types ?? [])));
            sort($modelInst);
            if ($inputInst !== $modelInst) {
                $validator->errors()->add('allowed_institution_types', 'Batasan tipe instansi tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
        }

        if ($this->has('required_entity_documents') && is_array($this->input('required_entity_documents'))) {
            $inputDocs = array_values(array_filter($this->input('required_entity_documents', [])));
            sort($inputDocs);
            $modelDocs = array_values(array_filter((array) ($category->required_entity_documents ?? [])));
            sort($modelDocs);
            if ($inputDocs !== $modelDocs) {
                $validator->errors()->add('required_entity_documents', 'Dokumen legalitas entitas tidak dapat diubah karena kategori sudah memiliki peserta terdaftar.');
            }
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
        if ($this->has('required_documents') && is_array($this->input('required_documents'))) {
            $inputDocs = array_values(array_filter($this->input('required_documents', [])));
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
        $data = $this->validator ? $this->validated() : $this->all();
        $category = $this->getCategory();
        $allowComm = $this->boolean('allow_community', $storeDefaults);
        $data['allow_club'] = $this->boolean('allow_club', $storeDefaults);
        $data['allow_community'] = $allowComm;

        if ($this->has('allow_individual')) {
            $data['allow_individual'] = $this->boolean('allow_individual');
        } elseif ($category !== null) {
            $data['allow_individual'] = (bool) $category->allow_individual;
        } else {
            $data['allow_individual'] = false;
        }

        if ($allowComm) {
            if ($this->has('allowed_community_subtypes')) {
                $data['allowed_community_subtypes'] = array_values(array_filter((array) $this->input('allowed_community_subtypes', [])));
            } elseif ($category !== null) {
                $data['allowed_community_subtypes'] = $category->allowed_community_subtypes;
            } elseif ($storeDefaults) {
                $data['allowed_community_subtypes'] = array_keys(EntityRequirements::CATEGORY_SUBTYPES);
            } else {
                $data['allowed_community_subtypes'] = null;
            }

            if ($this->has('allowed_institution_types')) {
                $data['allowed_institution_types'] = array_values(array_filter((array) $this->input('allowed_institution_types', [])));
            } elseif ($this->boolean('has_allowed_institution_types') || $this->boolean('clear_allowed_institution_types')) {
                $data['allowed_institution_types'] = [];
            } elseif ($category !== null) {
                $data['allowed_institution_types'] = $category->allowed_institution_types;
            } else {
                $data['allowed_institution_types'] = null;
            }

            if ($this->has('community_subtype')) {
                $data['community_subtype'] = $this->input('community_subtype');
            } elseif (! empty($data['allowed_community_subtypes'])) {
                $data['community_subtype'] = $data['allowed_community_subtypes'][0] ?? null;
            } elseif ($category !== null) {
                $data['community_subtype'] = $category->community_subtype;
            } else {
                $data['community_subtype'] = null;
            }
        } else {
            $data['allowed_community_subtypes'] = null;
            $data['allowed_institution_types'] = null;
            $data['community_subtype'] = null;
        }

        if ($this->has('required_entity_documents')) {
            $data['required_entity_documents'] = array_values(array_filter((array) $this->input('required_entity_documents', [])));
        } elseif ($this->boolean('has_required_entity_documents') || $this->boolean('clear_required_entity_documents')) {
            $data['required_entity_documents'] = [];
        } elseif ($category !== null) {
            $data['required_entity_documents'] = $category->required_entity_documents;
        } else {
            $data['required_entity_documents'] = null;
        }

        $data['enable_waiting_list'] = $this->boolean('enable_waiting_list', $storeDefaults);
        $data['is_roster_public'] = $this->boolean('is_roster_public', false);
        $data['document_instructions'] = $this->filled('document_instructions') ? trim((string) $this->input('document_instructions')) : null;
        $data['required_documents'] = $data['required_documents'] ?? [];
        $data['registration_fee'] = (float) ($data['registration_fee'] ?? 0);

        if ($this->has('max_teams_per_entity') && $this->input('max_teams_per_entity') !== null && $this->input('max_teams_per_entity') !== '') {
            $data['max_teams_per_entity'] = max(1, (int) $this->input('max_teams_per_entity'));
        } elseif ($storeDefaults) {
            $data['max_teams_per_entity'] = 1;
        } elseif ($category !== null) {
            $data['max_teams_per_entity'] = (int) ($category->max_teams_per_entity ?? 1);
        } else {
            $data['max_teams_per_entity'] = 1;
        }

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
            if ($data['allow_individual'] && ! $data['allow_club'] && ! $data['allow_community']) {
                $data['min_roster'] ??= 1;
                $data['max_roster'] ??= 1;
            } else {
                $data['min_roster'] ??= $data['match_type'] === '3x3' ? 3 : 5;
                $data['max_roster'] ??= $data['match_type'] === '3x3' ? 4 : 12;
            }
        }

        return $data;
    }
}
