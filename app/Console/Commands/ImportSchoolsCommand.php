<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\MasterData\Services\SchoolImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportSchoolsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'master-data:import-schools {--file= : Path ke file CSV sekolah}';

    /**
     * @var string
     */
    protected $description = 'Import master data sekolah dari file CSV';

    public function handle(SchoolImportService $service): int
    {
        $filePath = $this->option('file') ?: storage_path('app/imports/schools.csv');

        $this->info("Memulai impor data sekolah dari: {$filePath}");

        try {
            $result = $service->import((string) $filePath);

            $this->info("Impor data sekolah selesai. Diproses: {$result['processed']} baris (total dibaca: {$result['total_rows']}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
