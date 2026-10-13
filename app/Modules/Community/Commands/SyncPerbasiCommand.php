<?php

declare(strict_types=1);

namespace App\Modules\Community\Commands;

use App\Modules\Community\Services\PerbasiSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPerbasiCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'community:sync-perbasi {--file= : Path ke file JSON export PERBASI} {--dry-run : Simulasi tanpa menyimpan ke database}';

    /**
     * @var string
     */
    protected $description = 'Sinkronisasi komunitas dan atlet dari file export PERBASI (idempoten)';

    public function handle(PerbasiSyncService $service): int
    {
        $file = (string) $this->option('file');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($file)) {
            $this->error('Opsi --file wajib diisi.');

            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Memulai simulasi sinkronisasi data PERBASI (dry-run)...' : 'Memulai sinkronisasi data PERBASI...');

        try {
            $result = $service->syncFromFile($file, $dryRun);

            $this->info("Sinkronisasi selesai: {$result['communities_synced']} komunitas, {$result['athletes_synced']} atlet.");
            if ($dryRun) {
                $this->warn('Mode dry-run: tidak ada data yang ditulis ke database.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gagal melakukan sinkronisasi: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
