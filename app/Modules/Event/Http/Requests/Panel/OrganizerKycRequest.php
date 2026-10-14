<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use App\Contracts\CommunityLookup;
use App\Contracts\EntityRequirements;
use App\Modules\Event\Services\OrganizerRequirements;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizerKycRequest extends FormRequest
{
    public const BANKS = [
        'BCA' => 'Bank Central Asia (BCA)',
        'Mandiri' => 'Bank Mandiri',
        'BRI' => 'Bank Rakyat Indonesia (BRI)',
        'BNI' => 'Bank Negara Indonesia (BNI)',
        'BJB' => 'Bank BJB',
        'CIMB Niaga' => 'CIMB Niaga',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user()?->fresh();
        if ($user && $user->kyc_status === 'pending') {
            abort(422, 'Pengajuan verifikasi penyelenggara Anda sedang dalam peninjauan oleh admin.');
        }

        if (! $this->has('organizer_mode')) {
            $this->merge(['organizer_mode' => 'individual']);
        }

        if ($this->has('npwp_number') && (is_string($this->input('npwp_number')) || $this->input('npwp_number') === null)) {
            $rawNpwp = $this->input('npwp_number');
            if ($rawNpwp === null || trim((string) $rawNpwp) === '') {
                $this->merge(['npwp_number' => null]);
            } else {
                $cleaned = preg_replace('/[\.\-\s]/', '', (string) $rawNpwp);
                $this->merge(['npwp_number' => $cleaned]);
            }
        }

        if ($this->has('npwp_name') && (is_string($this->input('npwp_name')) || $this->input('npwp_name') === null)) {
            $rawName = $this->input('npwp_name');
            if ($rawName === null || trim((string) $rawName) === '') {
                $this->merge(['npwp_name' => null]);
            } else {
                $this->merge(['npwp_name' => trim((string) $rawName)]);
            }
        }

        if ($this->has('identity_name') && is_string($this->input('identity_name'))) {
            $this->merge(['identity_name' => trim($this->input('identity_name'))]);
        }

        if ($this->has('bank_account_holder') && is_string($this->input('bank_account_holder'))) {
            $this->merge(['bank_account_holder' => trim($this->input('bank_account_holder'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'organizer_mode' => ['required', 'string', 'in:individual,entity'],
            'community_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => is_string($this->input('organizer_mode')) && $this->input('organizer_mode') === 'entity'),
            ],
            'identity_number' => [
                'required',
                'string',
                'regex:/^\d{16}$/',
                Rule::unique('users', 'identity_number')->ignore($userId),
            ],
            'identity_name' => ['required', 'string', 'min:3', 'max:255'],
            'birth_place' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'identity_occupation' => ['required', 'string', 'max:255'],
            'identity_address' => ['required', 'string', 'max:500'],
            'bank_name' => ['required', 'string', Rule::in(array_keys(self::BANKS))],
            'bank_account_number' => ['required', 'string', 'regex:/^\d+$/', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'min:3', 'max:255'],
            'bank_branch' => ['required', 'string', 'min:3', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'province_id' => [
                'required',
                'string',
                'regex:/^\d{2}$/',
                Rule::exists('wilayah', 'kode'),
            ],
            'city_id' => [
                'required',
                'string',
                'regex:/^\d{2}\.\d{2}$/',
                Rule::exists('wilayah', 'kode'),
            ],
            'subdistrict_id' => [
                'required',
                'string',
                'regex:/^\d{2}\.\d{2}\.\d{2}$/',
                Rule::exists('wilayah', 'kode'),
            ],
            'village_id' => [
                'required',
                'string',
                'regex:/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/',
                Rule::exists('wilayah', 'kode'),
            ],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'rt' => ['nullable', 'string', 'regex:/^\d{1,3}$/'],
            'rw' => ['nullable', 'string', 'regex:/^\d{1,3}$/'],
            'npwp_number' => ['nullable', 'string', 'regex:/^\d{15,16}$/'],
            'npwp_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['required', 'string', 'max:20'],

            'identity_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'identity_selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'responsibility_statement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'authorization_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'documents' => ['nullable', 'array'],
            'documents.identity_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'documents.identity_selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'documents.responsibility_statement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'documents.bank_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'documents.authorization_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identity_number.unique' => 'Nomor KTP (NIK) sudah terdaftar dalam sistem.',
            'identity_number.regex' => 'Nomor KTP (NIK) harus terdiri dari 16 digit angka.',
            'npwp_number.unique' => 'Nomor NPWP sudah terdaftar dalam sistem.',
            'npwp_number.regex' => 'Nomor NPWP harus terdiri dari 15 atau 16 digit angka.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'postal_code.regex' => 'Kode pos harus terdiri dari 5 digit angka.',
            'rt.regex' => 'RT harus berupa angka 1-3 digit.',
            'rw.regex' => 'RW harus berupa angka 1-3 digit.',
            'community_id.required' => 'Komunitas wajib dipilih untuk pendaftaran mode entitas.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $rawMode = $this->input('organizer_mode');
            if (! is_string($rawMode)) {
                return;
            }
            $mode = $rawMode;

            // Reject any unknown nested document files
            $allFiles = $this->allFiles();
            if (isset($allFiles['documents']) && is_array($allFiles['documents'])) {
                $allowedDocKeys = ['identity_image', 'identity_selfie', 'responsibility_statement', 'bank_proof', 'authorization_letter'];
                foreach (array_keys($allFiles['documents']) as $docKey) {
                    if (! in_array($docKey, $allowedDocKeys, true)) {
                        $v->errors()->add("documents.{$docKey}", 'Berkas dokumen tidak dikenal.');
                    }
                }
            }

            $user = $this->user();
            $identityName = $this->input('identity_name');
            $holderName = $this->input('bank_account_holder');

            if ($mode === 'individual') {
                if (is_string($identityName) && is_string($holderName) && $identityName !== '' && $holderName !== '') {
                    if (trim(mb_strtolower($identityName)) !== trim(mb_strtolower($holderName))) {
                        $v->errors()->add('bank_account_holder', 'Nama pemilik rekening harus sama dengan nama identitas KTP.');
                    }
                }
            } elseif ($mode === 'entity') {
                $communityId = $this->input('community_id');
                if (! $communityId) {
                    $v->errors()->add('community_id', 'Komunitas wajib dipilih untuk pendaftaran penyelenggara entitas.');

                    return;
                }

                /** @var CommunityLookup $lookup */
                $lookup = app(CommunityLookup::class);
                $comm = $user ? $lookup->findForUser($user->id) : null;

                if (! $comm || (int) $communityId !== (int) $comm->id) {
                    $v->errors()->add('community_id', 'Komunitas tidak valid atau Anda bukan pengurus aktif komunitas ini.');

                    return;
                }

                if (! $comm->is_active || $comm->status !== 'verified') {
                    $v->errors()->add('community_id', 'Komunitas harus berstatus terverifikasi dan aktif sebelum dapat didaftarkan.');

                    return;
                }

                if ($comm->subtype === 'institution' && empty($comm->institution_type)) {
                    $v->errors()->add('community_id', 'Klasifikasi instansi komunitas belum lengkap. Lengkapi jenis instansi pada profil komunitas terlebih dahulu.');

                    return;
                }

                $requiredCodes = EntityRequirements::legalDocumentCodes((string) $comm->subtype, $comm->institution_type);
                if (empty($requiredCodes)) {
                    $v->errors()->add('community_id', 'Klasifikasi entitas komunitas belum lengkap.');

                    return;
                }

                $verifiedDocs = $lookup->legalDocumentsForUser($user->id, (int) $comm->id);
                $missing = array_diff($requiredCodes, array_keys($verifiedDocs));

                if (! empty($missing)) {
                    $v->errors()->add('community_id', 'Profil legalitas komunitas belum lengkap. Lengkapi dokumen wajib pada panel profil komunitas.');
                }
            }

            // Region hierarchy checks
            $provinceInput = $this->input('province_id');
            $cityInput = $this->input('city_id');
            $subdistrictInput = $this->input('subdistrict_id');
            $villageInput = $this->input('village_id');

            $provinceId = is_string($provinceInput) ? $provinceInput : '';
            $cityId = is_string($cityInput) ? $cityInput : '';
            $subdistrictId = is_string($subdistrictInput) ? $subdistrictInput : '';
            $villageId = is_string($villageInput) ? $villageInput : '';

            if ($provinceId !== '' && $cityId !== '' && ! str_starts_with($cityId, $provinceId.'.')) {
                $v->errors()->add('city_id', 'Kabupaten/Kota tidak sesuai dengan Provinsi yang dipilih.');
            }

            if ($cityId !== '' && $subdistrictId !== '' && ! str_starts_with($subdistrictId, $cityId.'.')) {
                $v->errors()->add('subdistrict_id', 'Kecamatan tidak sesuai dengan Kabupaten/Kota yang dipilih.');
            }

            if ($subdistrictId !== '' && $villageId !== '' && ! str_starts_with($villageId, $subdistrictId.'.')) {
                $v->errors()->add('village_id', 'Kelurahan/Desa tidak sesuai dengan Kecamatan yang dipilih.');
            }

            $npwpNumber = $this->input('npwp_number');
            $npwpName = $this->input('npwp_name');

            if (! empty($npwpNumber) && empty($npwpName)) {
                $v->errors()->add('npwp_name', 'Nama NPWP wajib diisi jika nomor NPWP disertakan.');
            }

            if (! empty($npwpName) && empty($npwpNumber)) {
                $v->errors()->add('npwp_name', 'Nama NPWP tidak boleh diisi tanpa nomor NPWP.');
            }

            // Documents presence check
            $organizerReq = app(OrganizerRequirements::class);
            $requiredDocCodes = $organizerReq->requiredDocumentCodes($mode === 'entity');

            // Reusable documents: user's non-rejected organizer docs
            $existingDocs = [];
            if ($user) {
                $existingDocs = $user->documents()
                    ->whereIn('category', OrganizerRequirements::categories())
                    ->where('status', '!=', 'rejected')
                    ->where('is_public', false)
                    ->get()
                    ->filter(fn ($d) => $d->file_path && Storage::disk('local')->exists($d->file_path))
                    ->map(fn ($doc) => Str::after((string) $doc->category, 'organizer_'))
                    ->all();

                // If mode changed to entity or community changed, authorization_letter cannot be reused
                $prevCommId = $user->organizer_community_id;
                $newCommId = (int) $this->input('community_id');
                if ($mode === 'entity' && ($prevCommId !== $newCommId || $prevCommId === null)) {
                    $existingDocs = array_values(array_diff($existingDocs, ['authorization_letter']));
                }
            }

            foreach ($requiredDocCodes as $code) {
                $hasFile = $this->hasFile($code) || $this->hasFile("documents.{$code}");
                $hasReusable = in_array($code, $existingDocs, true);

                if (! $hasFile && ! $hasReusable) {
                    $label = OrganizerRequirements::DOCUMENT_LABELS[$code] ?? $code;
                    $v->errors()->add($code, "Berkas {$label} wajib diunggah.");
                }
            }
        });
    }
}
