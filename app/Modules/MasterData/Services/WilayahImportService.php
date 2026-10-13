<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Wilayah;
use App\Modules\MasterData\Models\WilayahKodepos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WilayahImportService
{
    /**
     * @return array{wilayah_count: int, kodepos_count: int, skipped: bool}
     */
    public function import(?string $wilayahPath = null, ?string $kodeposPath = null, bool $force = false): array
    {
        $wilayahPath ??= base_path('wilayah.sql');
        $kodeposPath ??= base_path('wilayah_kodepos.sql');

        if (! file_exists($wilayahPath)) {
            throw new RuntimeException("Prasyarat hilang: file [{$wilayahPath}] tidak ditemukan.");
        }

        if (! file_exists($kodeposPath)) {
            throw new RuntimeException("Prasyarat hilang: file [{$kodeposPath}] tidak ditemukan.");
        }

        if (! Schema::hasTable('wilayah') || ! Schema::hasTable('wilayah_kodepos')) {
            throw new RuntimeException('Prasyarat hilang: tabel wilayah atau wilayah_kodepos belum ada di database.');
        }

        $existingCount = Wilayah::count();
        if ($existingCount > 0 && ! $force) {
            return [
                'wilayah_count' => $existingCount,
                'kodepos_count' => WilayahKodepos::count(),
                'skipped' => true,
            ];
        }

        $connection = DB::connection();
        $isSqlite = $connection->getDriverName() === 'sqlite';

        // Execute wilayah.sql
        $wilayahSql = file_get_contents($wilayahPath);
        if ($isSqlite) {
            $wilayahSql = str_replace('ENGINE=MyISAM;', ';', $wilayahSql);
        }
        $connection->unprepared($wilayahSql);

        // Execute wilayah_kodepos.sql
        $kodeposSql = file_get_contents($kodeposPath);
        $connection->unprepared($kodeposSql);

        return [
            'wilayah_count' => Wilayah::count(),
            'kodepos_count' => WilayahKodepos::count(),
            'skipped' => false,
        ];
    }
}
