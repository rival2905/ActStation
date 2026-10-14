<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchoolSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const LEVEL_MAP = [
        'sd' => 'sd',
        'mi' => 'sd',
        'smp' => 'smp',
        'mts' => 'smp',
        'sma' => 'sma',
        'ma' => 'sma',
        'smk' => 'smk',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('data/schools.csv');
        $jsonPath = database_path('data/schools.json');

        if (file_exists($csvPath)) {
            $this->seedFromCsv($csvPath);
        } elseif (file_exists($jsonPath)) {
            $this->seedFromJson($jsonPath);
        } else {
            throw new RuntimeException('Schools dataset not found in database/data/ (checked schools.csv and schools.json).');
        }
    }

    private function seedFromCsv(string $path): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Failed to open CSV file: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException("Empty CSV file: {$path}");
        }

        $headerMap = [];
        foreach ($header as $index => $column) {
            $headerMap[trim(strtolower((string) $column))] = $index;
        }

        $required = ['npsn', 'name', 'school_level', 'school_status'];
        foreach ($required as $col) {
            if (! isset($headerMap[$col])) {
                fclose($handle);
                throw new RuntimeException("Missing required column [{$col}] in {$path}");
            }
        }

        $seenNpsn = [];
        $batch = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $record = $this->parseRow($row, $headerMap, $rowNum, $seenNpsn);
            $batch[] = $record;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->upsertBatch($batch);
        }

        fclose($handle);
    }

    private function seedFromJson(string $path): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read JSON file: {$path}");
        }

        /** @var list<array<string, mixed>>|null $data */
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException("Invalid JSON format in {$path}");
        }

        $seenNpsn = [];
        $batch = [];
        $rowNum = 1;

        foreach ($data as $item) {
            $rowNum++;
            $record = $this->parseArray($item, $rowNum, $seenNpsn);
            $batch[] = $record;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     * @param  array<string, bool>  $seenNpsn
     * @return array<string, mixed>
     */
    private function parseRow(array $row, array $map, int $rowNum, array &$seenNpsn): array
    {
        $get = function (string $key) use ($row, $map): ?string {
            if (! isset($map[$key])) {
                return null;
            }
            $val = trim((string) ($row[$map[$key]] ?? ''));

            return $val === '' ? null : $val;
        };

        $npsn = $get('npsn');
        $this->validateNpsn($npsn, $rowNum, $seenNpsn);

        $name = $get('name') ?? $get('nama_sekolah');
        if ($name === null || $name === '') {
            throw new RuntimeException("Empty school name at row {$rowNum} for NPSN: {$npsn}");
        }

        $rawLevel = strtolower($get('school_level') ?? '');
        if (! isset(self::LEVEL_MAP[$rawLevel])) {
            throw new RuntimeException("Unknown school level [{$rawLevel}] at row {$rowNum} for NPSN: {$npsn}");
        }
        $schoolLevel = self::LEVEL_MAP[$rawLevel];

        $rawStatus = strtolower($get('school_status') ?? $get('status_sekolah') ?? '');
        if (! in_array($rawStatus, ['negeri', 'swasta'], true)) {
            throw new RuntimeException("Unknown school status [{$rawStatus}] at row {$rowNum} for NPSN: {$npsn}");
        }

        $cityId = $get('city_id') ?? $get('kode_kabupaten_kota');
        $provinceId = $get('province_id') ?? ($cityId !== null ? substr($cityId, 0, 2) : null);
        $subdistrictId = $get('subdistrict_id') ?? $get('kode_kecamatan');
        $villageId = $get('village_id');
        $address = $get('address') ?? $get('alamat');

        $latRaw = $get('latitude');
        $latitude = $latRaw !== null ? (float) $latRaw : null;

        $lngRaw = $get('longitude');
        $longitude = $lngRaw !== null ? (float) $lngRaw : null;

        $yearRaw = $get('dataset_year') ?? $get('tahun_dataset');
        $datasetYear = $yearRaw !== null ? (int) $yearRaw : null;

        $source = $get('source') ?? 'kemendikdasmen';

        return [
            'npsn' => $npsn,
            'name' => $name,
            'school_level' => $schoolLevel,
            'school_status' => $rawStatus,
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'subdistrict_id' => $subdistrictId,
            'village_id' => $villageId,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'dataset_year' => $datasetYear,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, bool>  $seenNpsn
     * @return array<string, mixed>
     */
    private function parseArray(array $item, int $rowNum, array &$seenNpsn): array
    {
        $npsn = isset($item['npsn']) ? trim((string) $item['npsn']) : null;
        $this->validateNpsn($npsn, $rowNum, $seenNpsn);

        $name = isset($item['name']) ? trim((string) $item['name']) : (isset($item['nama_sekolah']) ? trim((string) $item['nama_sekolah']) : '');
        if ($name === '') {
            throw new RuntimeException("Empty school name at item {$rowNum} for NPSN: {$npsn}");
        }

        $rawLevel = strtolower(trim((string) ($item['school_level'] ?? '')));
        if (! isset(self::LEVEL_MAP[$rawLevel])) {
            throw new RuntimeException("Unknown school level [{$rawLevel}] at item {$rowNum} for NPSN: {$npsn}");
        }
        $schoolLevel = self::LEVEL_MAP[$rawLevel];

        $rawStatus = strtolower(trim((string) ($item['school_status'] ?? $item['status_sekolah'] ?? '')));
        if (! in_array($rawStatus, ['negeri', 'swasta'], true)) {
            throw new RuntimeException("Unknown school status [{$rawStatus}] at item {$rowNum} for NPSN: {$npsn}");
        }

        $cityId = isset($item['city_id']) && trim((string) $item['city_id']) !== ''
            ? trim((string) $item['city_id'])
            : (isset($item['kode_kabupaten_kota']) && trim((string) $item['kode_kabupaten_kota']) !== '' ? trim((string) $item['kode_kabupaten_kota']) : null);

        $provinceId = isset($item['province_id']) && trim((string) $item['province_id']) !== ''
            ? trim((string) $item['province_id'])
            : ($cityId !== null ? substr($cityId, 0, 2) : null);

        $subdistrictId = isset($item['subdistrict_id']) && trim((string) $item['subdistrict_id']) !== ''
            ? trim((string) $item['subdistrict_id'])
            : (isset($item['kode_kecamatan']) && trim((string) $item['kode_kecamatan']) !== '' ? trim((string) $item['kode_kecamatan']) : null);

        $villageId = isset($item['village_id']) && trim((string) $item['village_id']) !== ''
            ? trim((string) $item['village_id'])
            : null;

        $address = isset($item['address']) && trim((string) $item['address']) !== ''
            ? trim((string) $item['address'])
            : (isset($item['alamat']) && trim((string) $item['alamat']) !== '' ? trim((string) $item['alamat']) : null);

        $latitude = isset($item['latitude']) && $item['latitude'] !== null && $item['latitude'] !== '' ? (float) $item['latitude'] : null;
        $longitude = isset($item['longitude']) && $item['longitude'] !== null && $item['longitude'] !== '' ? (float) $item['longitude'] : null;
        $datasetYear = isset($item['dataset_year']) && $item['dataset_year'] !== null && $item['dataset_year'] !== ''
            ? (int) $item['dataset_year']
            : (isset($item['tahun_dataset']) && $item['tahun_dataset'] !== null && $item['tahun_dataset'] !== '' ? (int) $item['tahun_dataset'] : null);

        $source = isset($item['source']) && trim((string) $item['source']) !== '' ? trim((string) $item['source']) : 'kemendikdasmen';

        return [
            'npsn' => $npsn,
            'name' => $name,
            'school_level' => $schoolLevel,
            'school_status' => $rawStatus,
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'subdistrict_id' => $subdistrictId,
            'village_id' => $villageId,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'dataset_year' => $datasetYear,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, bool>  $seenNpsn
     */
    private function validateNpsn(?string $npsn, int $rowNum, array &$seenNpsn): void
    {
        if ($npsn === null || ! preg_match('/^[0-9]{8}$/', $npsn)) {
            throw new RuntimeException("Invalid NPSN format [{$npsn}] at line {$rowNum}. NPSN must be exactly 8 digits.");
        }

        if (isset($seenNpsn[$npsn])) {
            throw new RuntimeException("Duplicate NPSN [{$npsn}] detected at line {$rowNum}.");
        }

        $seenNpsn[$npsn] = true;
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function upsertBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $now = now()->toDateTimeString();
        $driver = DB::getDriverName();

        $rowsSql = [];
        $bindings = [];

        foreach ($batch as $record) {
            $rowsSql[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = $record['npsn'];
            $bindings[] = $record['name'];
            $bindings[] = $record['school_level'];
            $bindings[] = $record['school_status'];
            $bindings[] = $record['province_id'];
            $bindings[] = $record['city_id'];
            $bindings[] = $record['subdistrict_id'];
            $bindings[] = $record['village_id'];
            $bindings[] = $record['address'];
            $bindings[] = $record['latitude'];
            $bindings[] = $record['longitude'];
            $bindings[] = $record['dataset_year'];
            $bindings[] = $record['source'];
            $bindings[] = $now;
            $bindings[] = $now;
        }

        $valuesClause = implode(', ', $rowsSql);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO `schools` (
                `npsn`, `name`, `school_level`, `school_status`,
                `province_id`, `city_id`, `subdistrict_id`, `village_id`,
                `address`, `latitude`, `longitude`, `dataset_year`, `source`,
                `created_at`, `updated_at`
            ) VALUES {$valuesClause}
            ON CONFLICT(`npsn`) DO UPDATE SET
                `name` = excluded.`name`,
                `school_level` = excluded.`school_level`,
                `school_status` = excluded.`school_status`,
                `province_id` = COALESCE(excluded.`province_id`, `schools`.`province_id`),
                `city_id` = COALESCE(excluded.`city_id`, `schools`.`city_id`),
                `subdistrict_id` = COALESCE(excluded.`subdistrict_id`, `schools`.`subdistrict_id`),
                `village_id` = COALESCE(excluded.`village_id`, `schools`.`village_id`),
                `address` = COALESCE(excluded.`address`, `schools`.`address`),
                `latitude` = COALESCE(excluded.`latitude`, `schools`.`latitude`),
                `longitude` = COALESCE(excluded.`longitude`, `schools`.`longitude`),
                `dataset_year` = COALESCE(excluded.`dataset_year`, `schools`.`dataset_year`),
                `source` = COALESCE(excluded.`source`, `schools`.`source`),
                `updated_at` = excluded.`updated_at`";
        } else {
            $sql = "INSERT INTO `schools` (
                `npsn`, `name`, `school_level`, `school_status`,
                `province_id`, `city_id`, `subdistrict_id`, `village_id`,
                `address`, `latitude`, `longitude`, `dataset_year`, `source`,
                `created_at`, `updated_at`
            ) VALUES {$valuesClause}
            ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`),
                `school_level` = VALUES(`school_level`),
                `school_status` = VALUES(`school_status`),
                `province_id` = COALESCE(VALUES(`province_id`), `schools`.`province_id`),
                `city_id` = COALESCE(VALUES(`city_id`), `schools`.`city_id`),
                `subdistrict_id` = COALESCE(VALUES(`subdistrict_id`), `schools`.`subdistrict_id`),
                `village_id` = COALESCE(VALUES(`village_id`), `schools`.`village_id`),
                `address` = COALESCE(VALUES(`address`), `schools`.`address`),
                `latitude` = COALESCE(VALUES(`latitude`), `schools`.`latitude`),
                `longitude` = COALESCE(VALUES(`longitude`), `schools`.`longitude`),
                `dataset_year` = COALESCE(VALUES(`dataset_year`), `schools`.`dataset_year`),
                `source` = COALESCE(VALUES(`source`), `schools`.`source`),
                `updated_at` = VALUES(`updated_at`)";
        }

        DB::statement($sql, $bindings);
    }
}
