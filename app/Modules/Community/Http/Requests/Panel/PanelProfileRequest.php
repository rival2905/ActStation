<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use App\Contracts\EntityRequirements;
use App\Contracts\SchoolLookup;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\CommunityLookupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PanelProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $subtype = (string) $this->input('subtype');
        if ($this->has('subtype') && ! in_array($subtype, ['school', 'school_sd', 'school_smp', 'school_sma'], true)) {
            $this->merge(['npsn' => null]);
        }
        if ($this->has('subtype') && $this->input('subtype') !== 'institution') {
            $this->merge(['institution_type' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $communityId = CommunityLookupService::resolveActiveCommunityId($this->user()?->id);

        return [
            'name' => ['required', 'string', 'max:255'],
            'subtype' => ['sometimes', 'required', 'string', Rule::in(['club', 'school_sd', 'school_smp', 'school_sma', 'school', 'university', 'institution', 'free_association'])],
            'institution_type' => [
                'nullable',
                Rule::requiredIf(function () use ($communityId) {
                    if ($this->input('subtype') !== 'institution') {
                        return false;
                    }
                    if ($communityId) {
                        $community = Community::find($communityId);
                        if ($community && in_array($community->status, ['pending', 'verified'], true) && is_null($community->institution_type)) {
                            return false;
                        }
                    }

                    return true;
                }),
                'string',
                'max:50',
                Rule::in(array_keys(EntityRequirements::INSTITUTION_TYPES)),
            ],
            'sport_category_id' => ['nullable', 'integer', Rule::exists('sport_categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'province_id' => ['required', 'string', 'max:10', Rule::exists('wilayah', 'kode')],
            'city_id' => ['required', 'string', 'max:10', Rule::exists('wilayah', 'kode')],
            'subdistrict_id' => ['nullable', 'string', 'max:15', Rule::exists('wilayah', 'kode')],
            'village_id' => ['nullable', 'string', 'max:15', Rule::exists('wilayah', 'kode')],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'npsn' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('subtype'), ['school', 'school_sd', 'school_smp', 'school_sma'], true)),
                'string',
                'max:20',
                Rule::unique('communities', 'npsn')->ignore($communityId),
            ],
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

            $communityId = DB::table('community_officials')
                ->where('user_id', $this->user()?->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->value('community_id');

            if ($communityId) {
                $community = Community::find($communityId);
                if ($community && in_array($community->status, ['pending', 'verified'], true)) {
                    $postedSubtype = (string) $this->input('subtype');
                    $normalizedPosted = in_array($postedSubtype, ['school_sd', 'school_smp', 'school_sma', 'school'], true) ? 'school' : $postedSubtype;
                    if ($this->has('subtype') && $normalizedPosted !== $community->subtype) {
                        $v->errors()->add('subtype', 'Klasifikasi tipe komunitas yang sedang ditinjau atau sudah terverifikasi tidak dapat diubah.');
                    }
                    if ($community->subtype === 'institution' && $this->has('institution_type') && $this->input('institution_type') !== $community->institution_type) {
                        $v->errors()->add('institution_type', 'Klasifikasi tipe instansi yang sedang ditinjau atau sudah terverifikasi tidak dapat diubah.');
                    }
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
}
