<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\School;
use RuntimeException;

class SchoolImportService
{
    /**
     * @return array{total_rows: int, processed: int}
     */
    public function import(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("Prasyarat hilang: file [{$filePath}] tidak ditemukan.");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Gagal membuka file [{$filePath}].");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException("File CSV kosong: [{$filePath}].");
        }

        $headerMap = [];
        foreach ($header as $index => $col) {
            $headerMap[trim(strtolower((string) $col))] = $index;
        }

        $chunk = [];
        $processed = 0;
        $totalRows = 0;
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;

            $npsn = $this->value($row, $headerMap, 'npsn');
            if (empty($npsn)) {
                continue;
            }

            $name = $this->value($row, $headerMap, 'name')
                ?? $this->value($row, $headerMap, 'nama_sekolah')
                ?? '';

            $schoolLevel = strtolower((string) (
                $this->value($row, $headerMap, 'school_level')
                ?? 'sd'
            ));

            $schoolStatus = strtolower((string) (
                $this->value($row, $headerMap, 'school_status')
                ?? $this->value($row, $headerMap, 'status_sekolah')
                ?? 'negeri'
            ));

            $cityId = $this->value($row, $headerMap, 'city_id')
                ?? $this->value($row, $headerMap, 'kode_kabupaten_kota');

            $provinceId = $this->value($row, $headerMap, 'province_id')
                ?? ($cityId ? substr((string) $cityId, 0, 2) : null);

            $subdistrictId = $this->value($row, $headerMap, 'subdistrict_id')
                ?? $this->value($row, $headerMap, 'kode_kecamatan');

            $villageId = $this->value($row, $headerMap, 'village_id');

            $address = $this->value($row, $headerMap, 'address')
                ?? $this->value($row, $headerMap, 'alamat');

            $latitude = $this->value($row, $headerMap, 'latitude');
            $longitude = $this->value($row, $headerMap, 'longitude');

            $datasetYear = $this->value($row, $headerMap, 'dataset_year')
                ?? $this->value($row, $headerMap, 'tahun_dataset');

            $source = $this->value($row, $headerMap, 'source') ?? 'kemendikdasmen';

            $chunk[] = [
                'npsn' => (string) $npsn,
                'name' => (string) $name,
                'school_level' => in_array($schoolLevel, ['sd', 'smp', 'sma', 'smk'], true) ? $schoolLevel : 'sd',
                'school_status' => in_array($schoolStatus, ['negeri', 'swasta'], true) ? $schoolStatus : 'negeri',
                'province_id' => $provinceId ? (string) $provinceId : null,
                'city_id' => $cityId ? (string) $cityId : null,
                'subdistrict_id' => $subdistrictId ? (string) $subdistrictId : null,
                'village_id' => $villageId ? (string) $villageId : null,
                'address' => $address ? (string) $address : null,
                'latitude' => $latitude !== null && $latitude !== '' ? (float) $latitude : null,
                'longitude' => $longitude !== null && $longitude !== '' ? (float) $longitude : null,
                'dataset_year' => $datasetYear !== null && $datasetYear !== '' ? (int) $datasetYear : null,
                'source' => (string) $source,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 500) {
                School::upsert($chunk, ['npsn'], [
                    'name',
                    'school_level',
                    'school_status',
                    'province_id',
                    'city_id',
                    'subdistrict_id',
                    'village_id',
                    'address',
                    'latitude',
                    'longitude',
                    'dataset_year',
                    'source',
                    'updated_at',
                ]);
                $processed += count($chunk);
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            School::upsert($chunk, ['npsn'], [
                'name',
                'school_level',
                'school_status',
                'province_id',
                'city_id',
                'subdistrict_id',
                'village_id',
                'address',
                'latitude',
                'longitude',
                'dataset_year',
                'source',
                'updated_at',
            ]);
            $processed += count($chunk);
        }

        fclose($handle);

        return [
            'total_rows' => $totalRows,
            'processed' => $processed,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     */
    protected function value(array $row, array $map, string $key): ?string
    {
        if (! isset($map[$key])) {
            return null;
        }

        $val = $row[$map[$key]] ?? null;
        if ($val === null) {
            return null;
        }

        $val = trim((string) $val);

        return $val === '' ? null : $val;
    }
}
