<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface AthleteDocumentVault
{
    /**
     * @param  array<int, string>  $codes
     * @return array<string, array{exists: bool, status: ?string}>
     */
    public function summaries(int $userId, array $codes): array;

    public function upload(int $userId, string $code, UploadedFile $file): void;
}
