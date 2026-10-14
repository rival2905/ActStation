<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CoverageMarkersRequest extends FormRequest
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
            'bbox' => ['nullable', 'string'],
            'province_id' => ['nullable', 'string', 'max:15'],
            'city_id' => ['nullable', 'string', 'max:15'],
            'region_code' => ['nullable', 'string', 'max:15'],
            'sport_category_id' => ['nullable', 'integer'],
            'school_level' => ['nullable', 'string', 'in:sd,smp,sma,smk,school_sd,school_smp,school_sma'],
            'layers' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $bbox = $this->input('bbox');
            $hasRegionFilter = $this->filled('province_id')
                || $this->filled('city_id')
                || $this->filled('region_code');

            if (empty($bbox) && ! $hasRegionFilter) {
                $v->errors()->add('bbox', 'Parameter bbox atau filter wilayah wajib disertakan.');

                return;
            }

            if (! empty($bbox)) {
                $coords = explode(',', (string) $bbox);
                if (count($coords) !== 4) {
                    $v->errors()->add('bbox', 'Format bbox harus south,west,north,east.');

                    return;
                }

                foreach ($coords as $coord) {
                    if (! is_numeric(trim($coord))) {
                        $v->errors()->add('bbox', 'Koordinat bbox harus berupa angka numerik.');

                        return;
                    }
                }

                [$south, $west, $north, $east] = array_map('floatval', $coords);
                if ($south < -90.0 || $south > 90.0 || $north < -90.0 || $north > 90.0) {
                    $v->errors()->add('bbox', 'Koordinat lintang (latitude) harus berada dalam rentang -90 hingga 90.');

                    return;
                }

                if ($west < -180.0 || $west > 180.0 || $east < -180.0 || $east > 180.0) {
                    $v->errors()->add('bbox', 'Koordinat bujur (longitude) harus berada dalam rentang -180 hingga 180.');

                    return;
                }

                if ($south > $north) {
                    $v->errors()->add('bbox', 'Batas selatan (south) tidak boleh melebihi batas utara (north).');

                    return;
                }
            }
        });
    }

    /**
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    public function parsedBbox(): ?array
    {
        $bbox = $this->input('bbox');
        if (empty($bbox)) {
            return null;
        }

        $coords = array_map('floatval', explode(',', (string) $bbox));
        if (count($coords) !== 4) {
            return null;
        }

        return [
            'south' => min($coords[0], $coords[2]),
            'west' => min($coords[1], $coords[3]),
            'north' => max($coords[0], $coords[2]),
            'east' => max($coords[1], $coords[3]),
        ];
    }

    /**
     * @return list<string>
     */
    public function layers(): array
    {
        $layers = $this->input('layers');
        if (empty($layers)) {
            return ['verified_comm', 'unverified_comm', 'venue', 'school'];
        }

        if (is_string($layers)) {
            $layers = explode(',', $layers);
        }

        return array_values(array_filter((array) $layers));
    }
}
