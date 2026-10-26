<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use RuntimeException;

class AssistantUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason = 'provider_unavailable')
    {
        parent::__construct($reason);
    }

    public function publicMessage(): string
    {
        return match ($this->reason) {
            'not_configured' => 'Asisten AI belum dikonfigurasi. Hubungi admin.',
            'connection_timeout' => 'Koneksi ke penyedia AI terputus atau melewati batas waktu. Pesan belum disimpan; coba lagi.',
            'invalid_response', 'tool_limit' => 'Jawaban penyedia AI belum dapat diproses. Pesan belum disimpan; coba lagi.',
            default => 'Penyedia AI sedang tidak tersedia. Pesan belum disimpan; coba lagi nanti.',
        };
    }
}
