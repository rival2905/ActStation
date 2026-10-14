<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use App\Contracts\EntityRequirements;
use App\Contracts\SchoolLookup;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $alreadyOfficial = CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        return ! $alreadyOfficial;
    }

    protected function prepareForValidation(): void
    {
        $subtype = (string) $this->input('subtype');
        if (! in_array($subtype, ['school', 'school_sd', 'school_smp', 'school_sma'], true)) {
            $this->merge(['npsn' => null]);
        }
        if ($subtype !== 'institution') {
            $this->merge(['institution_type' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subtype' => ['required', 'string', Rule::in(['club', 'school_sd', 'school_smp', 'school_sma', 'school', 'university', 'institution', 'free_association'])],
            'institution_type' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('subtype') === 'institution'),
                'string',
                'max:50',
                Rule::in(array_keys(EntityRequirements::INSTITUTION_TYPES)),
            ],
            'sport_category_id' => ['nullable', 'integer', Rule::exists('sport_categories', 'id')],
            'npsn' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('subtype'), ['school', 'school_sd', 'school_smp', 'school_sma'], true)),
                'string',
                'max:20',
                Rule::unique('communities', 'npsn'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:500'],
            'province_id' => ['required', 'string', 'max:10'],
            'city_id' => ['required', 'string', 'max:10'],
            'subdistrict_id' => ['nullable', 'string', 'max:15'],
            'village_id' => ['nullable', 'string', 'max:15'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_email' => ['required', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $provinceId = (string) $this->input('province_id');
            $cityId = (string) $this->input('city_id');
            $subdistrictId = (string) $this->input('subdistrict_id');
            $villageId = (string) $this->input('village_id');

            if ($provinceId !== '' && $cityId !== '' && str_contains($cityId, '.')) {
                if (! str_starts_with($cityId, $provinceId.'.')) {
                    $v->errors()->add('city_id', 'Kabupaten/Kota tidak sesuai dengan Provinsi yang dipilih.');
                }
            }

            if ($cityId !== '' && $subdistrictId !== '' && str_contains($subdistrictId, '.')) {
                if (! str_starts_with($subdistrictId, $cityId.'.')) {
                    $v->errors()->add('subdistrict_id', 'Kecamatan tidak sesuai dengan Kabupaten/Kota yang dipilih.');
                }
            }

            if ($subdistrictId !== '' && $villageId !== '' && str_contains($villageId, '.')) {
                if (! str_starts_with($villageId, $subdistrictId.'.')) {
                    $v->errors()->add('village_id', 'Kelurahan/Desa tidak sesuai dengan Kecamatan yang dipilih.');
                }
            }

            $hasLat = $this->input('latitude') !== null && $this->input('latitude') !== '';
            $hasLng = $this->input('longitude') !== null && $this->input('longitude') !== '';
            if ($hasLat !== $hasLng) {
                if (! $hasLat) {
                    $v->errors()->add('latitude', 'Koordinat latitude wajib diisi jika longitude diisi.');
                }
                if (! $hasLng) {
                    $v->errors()->add('longitude', 'Koordinat longitude wajib diisi jika latitude diisi.');
                }
            }

            $subtype = (string) $this->input('subtype');
            if (in_array($subtype, ['school', 'school_sd', 'school_smp', 'school_sma'], true)) {
                $npsn = trim((string) $this->input('npsn'));
                if ($npsn !== '') {
                    /** @var SchoolLookup $schoolLookup */
                    $schoolLookup = app(SchoolLookup::class);
                    $school = $schoolLookup->findByNpsn($npsn);
                    if (! $school) {
                        $v->errors()->add('npsn', 'NPSN tidak ditemukan dalam basis data master sekolah.');
                    } else {
                        $level = strtolower($school->school_level);
                        $valid = match ($subtype) {
                            'school_sd' => $level === 'sd',
                            'school_smp' => $level === 'smp',
                            'school_sma' => in_array($level, ['sma', 'smk'], true),
                            'school' => in_array($level, ['sd', 'smp', 'sma', 'smk'], true),
                            default => true,
                        };

                        if (! $valid) {
                            $v->errors()->add('npsn', "Jenjang sekolah ({$school->school_level}) tidak sesuai dengan sub-tipe yang dipilih.");
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subtype.in' => 'Sub-tipe komunitas tidak valid.',
            'npsn.required' => 'NPSN wajib diisi untuk komunitas sekolah.',
            'npsn.required_if' => 'NPSN wajib diisi untuk komunitas sekolah.',
            'npsn.unique' => 'NPSN sudah terdaftar pada komunitas lain.',
            'contact_person.required' => 'Nama kontak penanggung jawab (PIC) wajib diisi.',
            'contact_phone.required' => 'Nomor telepon penanggung jawab wajib diisi.',
            'contact_email.required' => 'Email kontak penanggung jawab wajib diisi.',
            'province_id.required' => 'Provinsi wajib dipilih.',
            'city_id.required' => 'Kota/Kabupaten wajib dipilih.',
        ];
    }
}
