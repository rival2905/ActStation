<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\MasterData\Services\WilayahImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportWilayahCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'master-data:import-wilayah {--force : Timpa jika data sudah ada}';

    /**
     * @var string
     */
    protected $description = 'Import master data wilayah dan wilayah kodepos dari file SQL';

    public function handle(WilayahImportService $service): int
    {
        $this->info('Memulai impor master data wilayah...');

        try {
            $force = (bool) $this->option('force');
            $result = $service->import(force: $force);

            if ($result['skipped']) {
                $this->warn("Data wilayah sudah ada ({$result['wilayah_count']} wilayah, {$result['kodepos_count']} kodepos). Gunakan --force untuk menimpa.");
            } else {
                $this->info("Impor wilayah berhasil: {$result['wilayah_count']} wilayah, {$result['kodepos_count']} kodepos.");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
